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

use CRM_Statisticscalc_ExtensionUtil as E;

class CRM_Statistics_CaseStatistics {

  /**
   * @param array $entityIDs
   *
   * @return array
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function calculate($entityIDs) {
    CRM_Statistics_Utils_Hook::getStatisticsMetadata($metadata);
    $results = [];
    if (!empty($metadata['case'])) {
      foreach ($metadata['case'] as $calculations) {
        if (!class_exists($calculations['class'])) {
          continue;
        }
        if (!in_array($calculations['method'], get_class_methods($calculations['class']))) {
          continue;
        }
        $statisticsMetadata = call_user_func([$calculations['class'], $calculations['method']]);
        foreach ($entityIDs as $entityID) {
          // Make sure we are not triggered again (by hookCaseChangeCalculateScoresCallback)
          if (isset(Civi::$statics[E::LONG_NAME]['generate'][$entityID]['handled'])) {
            return [];
          }

          $activityFields = CRM_Statistics_CaseStatistics::getCaseActivityFields(
            $entityID,
            $statisticsMetadata['activityTypesCopyField']
          );
          $activityCounts = CRM_Statistics_CaseStatistics::getCaseActivityCounts(
            $entityID,
            $statisticsMetadata['activityTypesCount']
          );
          $caseParamsByName = array_merge(
            $activityFields,
            $activityCounts
          );

          foreach ($caseParamsByName as $caseCustomFieldName => $caseCustomFieldValue) {
            $customFieldParts = explode('.', $caseCustomFieldName);
            // Convert NULL to string 'null' for API3 to save as NULL
            $caseParams[CRM_Statistics_Utils::getCustomByName($customFieldParts[1], $customFieldParts[0], TRUE)]
              = ($caseCustomFieldValue ?? 'null');
          }

          $caseParams['id'] = $entityID;
          Civi::$statics[E::LONG_NAME]['generate'][$entityID]['handled'] = TRUE;
          $results[$entityID] = civicrm_api3('Case', 'create', $caseParams)['values'][$entityID];
        }
      }
    }
    return $results;
  }

  /**
   * Returns a list of activity counts for each requested case field
   *
   * @param int $caseId
   * @param array $activityTypesCount Description of activities
   *  eg. [
   *        'activityTypes' => [
   *          'Assessment Notes' => [ // activity type name
   *            'Case_Statistics.Assessment_Completed_' => 'Completed', // result field => activity status name
   *            'Case_Statistics.Assessment_Cancelled_' => 'Cancelled',
   *          ]
   *        ]
   *      ]
   *
   * @return array
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function getCaseActivityCounts($caseId, $activityTypesCount) {
    if (empty($caseId)) {
      throw new CRM_Core_Exception('getCaseActivityCounts: Must specify case ID!');
    }
    if (empty($activityTypesCount['activityTypes'])) {
      // We won't do anything if we have no activity types/states
      return [];
    }

    foreach ($activityTypesCount['activityTypes'] as $activityTypeName => $activityResultFields) {
      foreach ($activityResultFields as $resultField => $activityStatus) {
        $activityCountParams = [
          'activity_type_id' => $activityTypeName,
          'case_id' => $caseId,
          'is_current_revision' => 1,
          'is_deleted' => 0,
          'status_id' => $activityStatus,
        ];
        $count = civicrm_api3('Activity', 'getcount', $activityCountParams);
        $fields[$resultField] = $count;
      }
    }
    return $fields ?? [];
  }

  /**
   * Returns an array of fields with values copied from another field
   *
   * @param int $caseId
   * @param array $activityTypesField Array describing what to return
   * activityTypeName => [
   *   caseCustomFieldName => activityCustomField
   * ]
   *
   * @return array
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function getCaseActivityFields($caseId, $activityTypesField) {
    $activityFieldsToReturn = ['activity_type_id' => 'activity_type_id'];
    foreach ($activityTypesField as $activityTypeName => $fieldMap) {
      foreach ($fieldMap as $caseFieldName => $activityFieldName)
      $activityFieldsToReturn[$activityFieldName] = $activityFieldName;
    }

    if (empty($caseId)) {
      Throw new CRM_Core_Exception('getCaseActivityFields: Must specify case ID!');
    }
    if (empty($activityTypesField)) {
      // We won't do anything if we have no activity types/states
      return [];
    }

    $fields = [];
    foreach ($activityTypesField as $activityTypeName => $fieldMap) {
      if (empty($activityTypeName)) {
        // This should only happen in a test environment, but we don't want to process if we don't have an ID
        continue;
      }
      try {
        $activities = civicrm_api3('Activity', 'get', [
          'return' => $activityFieldsToReturn,
          'activity_type_id' => $activityTypeName,
          'is_current_revision' => 1,
          'is_deleted' => 0,
          'case_id' => $caseId,
          'options' => ['sort' => "id DESC", 'limit' => 1],
        ])['values'];
      }
      catch (Exception $e) {
        \Civi::log()->error(__CLASS__ . '::' . __FUNCTION__ . ' ' . $e->getMessage());
        continue;
      }

      // If we have values for the activity set them. Otherwise return NULL for those fields
      $activities = reset($activities);
      foreach ($fieldMap as $caseFieldName => $activityFieldName) {
        $fields[$caseFieldName] = $activities[$activityFieldName] ?? NULL;
      }
    }
    return $fields;
  }

  /**
   * Triggered via symfony event when a case is changed
   *
   * @param \Civi\Core\Event\GenericHookEvent $event
   * @param string $hook
   */
  public static function hookCaseChangeCalculateScores($event, $hook) {
    /* var $event->analyzer = \Civi\CCase\Analyzer */
    if (empty($event->analyzer->getCaseId())) {
      return;
    }

    if (!isset(Civi::$statics[__CLASS__]['hookCaseChangeCalculateScoresCallback'])) {
      register_shutdown_function(["CRM_Statistics_CaseStatistics", "hookCaseChangeCalculateScoresCallback"]);
      Civi::$statics[__CLASS__]['hookCaseChangeCalculateScoresCallback'] = TRUE;
      if (!isset(Civi::$statics[__CLASS__]['generate'][$event->analyzer->getCaseId()])) {
        Civi::$statics[__CLASS__]['generate'][$event->analyzer->getCaseId()] = [];
      }
    }
  }

  /**
   * Callback for hookCaseChangeCalculateScores
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function hookCaseChangeCalculateScoresCallback() {
    if (!isset(Civi::$statics[__CLASS__]['generate'])) {
      return;
    }
    foreach (Civi::$statics[__CLASS__]['generate'] as $caseID => $detail) {
      if (isset(Civi::$statics[__CLASS__]['generate'][$caseID]['handled'])) {
        continue;
      }
      Civi::$statics[__CLASS__]['generate'][$caseID]['handled'] = TRUE;
      civicrm_api3('CaseStatistics', 'generate', ['case_id' => $caseID]);
    }
  }

}
