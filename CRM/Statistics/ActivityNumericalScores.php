<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

class CRM_Statistics_ActivityNumericalScores {
  const CALC_MODE_INT_SUM = 1; // Sum of integer fields

  /**
   * Calculate the scores
   *
   * @param array $activityIds
   * @param array $options
   *   limit and offset per API3
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function calculate($activityIds, $options) {
    CRM_Statistics_Utils_Hook::getStatisticsMetadata($metadata);
    $results = [];
    if (!empty($metadata['activity'])) {
      foreach($metadata['activity'] as $activityCalculations) {
        if (!class_exists($activityCalculations['class'])) {
          continue;
        }
        if (!in_array($activityCalculations['method'], get_class_methods($activityCalculations['class']))) {
          continue;
        }
        $statisticsMetadata = call_user_func([$activityCalculations['class'], $activityCalculations['method']]);
        $results[] = self::runCalculations($statisticsMetadata, $activityIds, $options);
      }
    }
    return $results;
  }

  /**
   * Get all activity IDs for activity type id.  If $activityIds array is specified we use that as a filter
   *   and remove any that are not of the specified activity type.
   *
   * @param int $activityTypeName The activity Type Name
   * @param array $activityIds Array of valid IDs (if NULL, we retrieve all, if array of IDs we filter those IDs).
   * @param array $options
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function getActivityIdsByActivityType($activityTypeName, $activityIds = [], $options) {
    $activityParams = [
      'return' => ['id'],
      'activity_type_id' => $activityTypeName,
      'is_current_revision' => 1,
      'options' => $options,
    ];
    if (!empty($activityIds)) {
      $activityParams['id'] = ['IN' => $activityIds];
    }
    try {
      $activityIdsResult = civicrm_api3('Activity', 'get', $activityParams);
    }
    catch (Exception $e) {
      \Civi::log()->error(__CLASS__ . '::' . __FUNCTION__ . ' ' . $e->getMessage());
      return [];
    }
    return array_keys($activityIdsResult['values']);
  }

  /**
   * @param $calculationsMetadata
   * @param array $activityIds
   * @param array $options
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function runCalculations($calculationsMetadata, $activityIds = [], $options) {
    foreach ($calculationsMetadata as $activityTypeName => $calculations) {
      $filteredActivityIds = self::getActivityIdsByActivityType($activityTypeName, $activityIds, $options);
      foreach ($filteredActivityIds as $activityId) {
        // Get the details of the original activity
        $activityDetails = self::getActivityDetailsForCalculations($activityId, $calculations);

        // Run through each of the calculations
        $resultsToSave=FALSE;
        $results = [];
        foreach ($calculations as $index => $metadata) {
          // Subject matching - if we specify a subject in the metadata we can perform different calculations depending on the activity subject
          if (!empty($metadata['subject'])) {
            if ($metadata['subject'] !== $activityDetails['subject']) {
              continue;
            }
          }
          switch ($metadata['mode']) {
            case self::CALC_MODE_INT_SUM:
              foreach ($metadata['sourceFields'] as $customFieldName) {
                $customFieldNameParts = explode('.', $customFieldName);
                if (count($customFieldNameParts) === 2) {
                  // It's in format case.custom_123
                  $sourceFieldType = $customFieldNameParts[0];
                  $sourceFieldName = $customFieldNameParts[1];
                }
                else {
                  // It's just a customfield name so we assume type = activity
                  $sourceFieldType = 'activity';
                  $sourceFieldName = $customFieldName;
                }
                if (isset($results[$sourceFieldType][$sourceFieldName])) {
                  $activityDetails["{$sourceFieldType}.{$sourceFieldName}"] = $results[$sourceFieldType][$sourceFieldName];
                }
              }
              $result = self::calculateIntegerSum($activityDetails, $metadata['sourceFields']);
              if ($result !== NULL) {
                // Check if the result matches:
                // - A supported entity AND
                // - (result is not set OR result is not equal to existing result)
                if ((($metadata['resultEntity'] === 'activity') && (!isset($activityDetails[$metadata['resultField']]) || ($result !== (int) $activityDetails[$metadata['resultField']])))
                   || (($metadata['resultEntity'] === 'case') && (!isset($activityDetails["case_id.{$metadata['resultField']}"]) || ($result !== (int) $activityDetails["case_id.{$metadata['resultField']}"])))) {
                  // We only save the result if it's not NULL (ie. some questions have been answered) and it is different from the saved value
                  $resultsToSave = TRUE;
                  $results[$metadata['resultEntity']][$metadata['resultField']] = $result;
                  $counts['numberOfChangedCalculations'][$metadata['name']] ?? $counts['numberOfChangedCalculations'][$metadata['name']] = 0;
                  $counts['numberOfChangedCalculations'][$metadata['name']] += 1;
                }
              }
            break;
          }

          isset($counts['numberOfCalculations'][$metadata['name']]) ?: $counts['numberOfCalculations'][$metadata['name']] = 0;
          $counts['numberOfCalculations'][$metadata['name']] = CRM_Utils_Array::value($metadata['name'], $counts['numberOfCalculations']) + 1;
        }
        if ($resultsToSave) {
          foreach ($results as $entityName => $entityResults) {
            if (empty($entityName)) {
              continue;
            }
            switch ($entityName) {
              case 'case':
                if (empty($activityDetails['case_id'])) {
                  continue 2;
                }
                $entityResults['id'] = CRM_Utils_Array::first($activityDetails['case_id']);
                break;

              case 'activity':
                $entityResults['id'] = $activityId;
                if (!empty($activityDetails['case_id'])) {
                  // Need to specify so "is_current_revision" is handled correctly
                  $entityResults['case_id'] = CRM_Utils_Array::first($activityDetails['case_id']);
                }
                break;

            }
            civicrm_api3(ucfirst($entityName), 'create', $entityResults);
          }
        }
      }
    }
    return $counts ?? [];
  }

  /**
   * Get all parameters required for all calculations for this activity
   *
   * @param int $activityId
   * @param array $calculations
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private static function getActivityDetailsForCalculations($activityId, $calculations) {
    $return = ['case_id', 'subject'];
    foreach ($calculations as $index => $metadata) {
      // Get the details of the original activity
      foreach ($metadata['sourceFields'] as $field) {
        $return[] = $field;
      }
      // Retrieve the result field (joining) depending on the entityType.
      switch ($metadata['resultEntity']) {
        case 'activity':
          $return[] = $metadata['resultField'];
          break;

        case 'case':
          $return[] = "case.{$metadata['resultField']}";
          break;
      }
    }
    $originalActivityParams = [
      'id' => $activityId,
      'return' => array_values($return),
    ];
    return civicrm_api3('Activity', 'getsingle', $originalActivityParams);
  }

  /**
   * Add together all $sourceFieldNames for the activity ID.
   *
   * @param array $activityDetails
   * @param array $sourceFieldNames (eg. custom_123, custom_124)
   *
   * @return int|null if none of the sourceFieldNames are set
   */
  public static function calculateIntegerSum($activityDetails, $sourceFieldNames) {
    $total = NULL;
    foreach ($sourceFieldNames as $sourceFieldName) {
      if (isset($activityDetails[$sourceFieldName])) {
        $total += (int) $activityDetails[$sourceFieldName];
      }
    }
    return $total;
  }

  /**
   * Symfony event handler for hook_civicrm_post for callbackCalculateActivityScores
   *
   * @param \Civi\Core\Event\GenericHookEvent $event
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function callbackPostCalculateActivityScores($event) {
    if ($event->entity !== 'Activity') {
      return;
    }

    $validActions = ['create', 'edit'];
    if (!in_array($event->action, $validActions)) {
      return;
    }

    CRM_Statistics_ActivityNumericalScores::callbackCalculateActivityScores($event->id);
  }

  /**
   * @param int $activityId
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function callbackCalculateActivityScores($activityId) {
    civicrm_api3('CaseStatistics', 'calculate_scores', ['activity_id' => $activityId]);
  }

}
