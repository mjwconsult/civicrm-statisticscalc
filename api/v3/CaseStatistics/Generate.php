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
 * CaseStatistics.Generate API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_case_statistics_generate_spec(&$spec) {
  $spec['case_id']['api.required'] = 0;
  $spec['case_id']['title'] = 'The Case ID (optional)';
  $spec['case_id']['description'] = 'If not specified, statistics will be calculated for all cases';
}

/**
 * CaseStatistics.Generate API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @throws \CiviCRM_API3_Exception
 */
function civicrm_api3_case_statistics_generate($params) {
  if (!empty($params['case_id'])) {
    $caseStatistics = CRM_Statistics_CaseStatistics::calculate([$params['case_id']]);
    return civicrm_api3_create_success($caseStatistics, $params, 'CaseStatistics', 'Generate');
  }
  $caseIdsResult = civicrm_api3('Case', 'get', [
    'return' => ['id'],
    'options' => ['limit' => 0],
  ]);
  $caseIds = array_keys($caseIdsResult['values']);

  CRM_Statistics_CaseStatistics::calculate($caseIds);

  $result['count'] = count($caseIds);
  return civicrm_api3_create_success($result, $params, 'CaseStatistics', 'Generate');
}
