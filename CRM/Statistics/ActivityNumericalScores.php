<?php

class CRM_Statistics_ActivityNumericalScores {
  const CALC_MODE_INT_SUM = 1; // Sum of integer fields

  /**
   * Calculate the scores
   *
   * @param int|array|null $activityIds
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function calculate($activityIds = NULL) {
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
        $results[] = self::runCalculations($statisticsMetadata, $activityIds);
      }
    }
    return $results;
  }

  /**
   * Get all activity IDs for activity type id.  If $activityIds array is specified we use that as a filter
   *   and remove any that are not of the specified activity type.
   *
   * @param int $activityTypeId The activity Type ID
   * @param array $activityIds Array of valid IDs (if NULL, we retrieve all, if array of IDs we filter those IDs).
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function getActivityIdsByActivityType($activityTypeId, $activityIds = NULL) {
    $activityParams = array(
      'return' => array("id"),
      'activity_type_id' => $activityTypeId,
      'is_current_revision' => 1,
      'options' => array('limit' => 0),
    );
    if (!empty($activityIds)) {
      $activityParams['id'] = ['IN' => $activityIds];
    }
    $activityIdsResult = civicrm_api3('Activity', 'get', $activityParams);
    return array_keys($activityIdsResult['values']);
  }

  /**
   * @param $calculationsMetadata
   * @param null $activityIds
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function runCalculations($calculationsMetadata, $activityIds = NULL) {
    $counts = array();
    foreach ($calculationsMetadata as $activityTypeId => $calculations) {
      $filteredActivityIds = self::getActivityIdsByActivityType($activityTypeId, $activityIds);
      foreach ($filteredActivityIds as $activityId) {
        // Get the details of the original activity
        $activityDetails = self::getActivityDetailsForCalculations($activityId, $calculations);

        // Run through each of the calculations
        $resultsToSave=FALSE;
        foreach ($calculations as $index => $metadata) {
          // Subject matching - if we specify a subject in the metadata we can perform different calculations depending on the activity subject
          if (!empty($metadata['subject'])) {
            if ($metadata['subject'] !== $activityDetails['subject']) {
              continue;
            }
          }
          switch ($metadata['mode']) {
            case self::CALC_MODE_INT_SUM:
              $result = self::calculateIntegerSum($activityDetails, $metadata['sourceFields']);
              if (($result !== NULL) && ($result !== (int) $activityDetails[$metadata['resultField']])) {
                // We only save the result if it's not NULL (ie. some questions have been answered) and it is different from the saved value
                $resultsToSave=TRUE;
                $results[$metadata['resultEntity']][$metadata['resultField']] = $result;
                $counts['numberOfChangedCalculations'][$metadata['name']] += 1;
              }
            break;
          }
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
                  continue;
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
    return $counts;
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
      $return[] = $metadata['resultField'];
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

  public static function callbackCalculateActivityScores($activityId) {
    civicrm_api3('CaseStatistics', 'calculate_scores', ['activity_id' => $activityId]);
  }

  /**
   * Symfony event handler for hook_civicrm_post for callbackCalculateActivityScores
   *
   * @param $event
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function callbackPostCalculateActivityScores($event) {
    $params = $event->getHookValues();
    if (count($params) < 4) {
      return;
    }
    $hookParams = [
      'op' => $params[0],
      'entity' => $params[1],
      'id' => $params[2],
      'object' => $params[3],
    ];

    if ($hookParams['entity'] !== 'Activity') {
      return;
    }

    $validActions = ['create', 'edit'];
    if (!in_array($hookParams['op'], $validActions)) {
      return;
    }

    if (CRM_Core_Transaction::isActive()) {
      CRM_Core_Transaction::addCallback(CRM_Core_Transaction::PHASE_POST_COMMIT, 'CRM_Statistics_ActivityNumericalScores::callbackCalculateActivityScores', [$hookParams['id']]);
    }
    else {
      CRM_Statistics_ActivityNumericalScores::callbackCalculateActivityScores($hookParams['id']);
    }
  }

}
