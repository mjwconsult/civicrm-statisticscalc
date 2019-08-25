<?php
use CRM_TheHarbour_ExtensionUtil as E;

/**
 * CaseStatistics.Generate API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_case_statistics_Generate_spec(&$spec) {
  $spec['case_id']['api.required'] = 0;
  $spec['case_id']['title'] = 'The Case ID (optional)';
  $spec['case_id']['description'] = 'If not specified, statistics will be calculated for all cases';
}

/**
 * CaseStatistics.Generate API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws \CiviCRM_API3_Exception
 */
function civicrm_api3_case_statistics_Generate($params) {
  if (!empty($params['case_id'])) {
    $caseStatistics = CRM_Statistics_CaseStatistics::generateCaseStatistics($params['case_id']);
    return civicrm_api3_create_success($caseStatistics, $params, 'CaseStatistics', 'Generate');
  }
  $caseIdsResult = civicrm_api3('Case', 'get', array(
    'return' => array("id"),
    'options' => array('limit' => 0),
  ));
  $caseIds = array_keys($caseIdsResult['values']);

  foreach ($caseIds as $caseId) {
    CRM_Statistics_CaseStatistics::generateCaseStatistics($caseId);
  }
  $result['count'] = count($caseIds);
  return civicrm_api3_create_success($result, $params, 'CaseStatistics', 'Generate');
}
