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
            $caseParams[CRM_Statistics_Utils::getCustomByName($customFieldParts[1], $customFieldParts[0], TRUE)] = $caseCustomFieldValue;
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
   * @param array $activityTypeIds Array describing what to return
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
      $activities = civicrm_api3('Activity', 'get', [
        'return' => $activityFieldsToReturn,
        'activity_type_id' => $activityTypeName,
        'is_current_revision' => 1,
        'is_deleted' => 0,
        'case_id' => $caseId,
        'options' => ['sort' => "id DESC", 'limit' => 1],
      ])['values'];

      if (!empty($activities)) {
        $activities = reset($activities);
        foreach ($fieldMap as $caseFieldName => $activityFieldName) {
          $fields[$caseFieldName] = $activities[$activityFieldName];
        }
      }
    }
    return $fields;
  }

}
