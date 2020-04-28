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

/**
 * CaseStatistics.Calculate_Scores API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_case_statistics_calculate_scores_spec(&$spec) {
  $spec['activity_id']['api.required'] = 0;
  $spec['activity_id']['title'] = 'The Activity ID';
  $spec['activity_id']['description'] = 'Accepts multiple IDs as array. If not specified, scores will be calculated for all activities';
}

/**
 * CaseStatistics.Calculate_Scores API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws \CiviCRM_API3_Exception
 */
function civicrm_api3_case_statistics_calculate_scores($params) {
  if (!empty($params['activity_id'])) {
    if (isset($params['activity_id']['IN'])) {
      $params['activity_id'] = $params['activity_id']['IN'];
    }
    if (!is_array($params['activity_id'])) {
      $params['activity_id'] = [$params['activity_id']];
    }
    $result = CRM_Statistics_ActivityNumericalScores::calculate($params['activity_id']);
    return civicrm_api3_create_success(array($result), $params, 'CaseStatistics', 'Calculate_Scores');
  }
  $result = CRM_Statistics_ActivityNumericalScores::calculate();
  return civicrm_api3_create_success(array($result), $params, 'CaseStatistics', 'Calculate_Scores');
}
