<?php
use CRM_TheHarbour_ExtensionUtil as E;

/**
 * CaseStatistics.Calculate_Scores API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_case_statistics_Calculate_Dailystatus_spec(&$spec) {
  $spec['date']['api.required'] = 0;
  $spec['date']['title'] = 'The date to calculate for';
  $spec['date']['description'] = 'If not specified, status counts will be calculated for the current day';
  $spec['date']['type'] = CRM_Utils_Type::T_DATE;
  $spec['backfill']['title'] = 'Recalculate all dates';
  $spec['backfill']['type'] = CRM_Utils_Type::T_BOOLEAN;
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
function civicrm_api3_case_statistics_Calculate_Dailystatus($params) {
  CRM_Statistics_CaseStatus::createTargetTable();
  CRM_Statistics_CaseStatus::createSourceTable();
  CRM_Statistics_CaseStatus::createSourceDataForStatusCounts();

  $startDate = NULL;
  if (!empty($params['date'])) {
    $lastDate = new DateTime($params['date']);
    $startDate = clone $lastDate;
  }
  else {
    $lastDate = new DateTime('now');
    // By default if no params are specified we calculate all missing days from the last day that was calculated.
    $startDate = CRM_Statistics_CaseStatus::getLastDateForStatusCounts();
    $startDate->modify(new DateInterval('P1D'));
  }
  $lastDate->setTime(0,0,0);

  // If we are backfilling, or we have no existing data
  if (!empty($params['backfill']) || !$startDate) {
    $startDate = CRM_Statistics_CaseStatus::getStartDateForStatusCounts();
  }
  while ($lastDate >= $startDate) {
    CRM_Statistics_CaseStatus::updateStatusCountsForDate($startDate);
    $startDate->add(new DateInterval('P1D'));
  }
  return civicrm_api3_create_success([], $params, 'CaseStatistics', 'CalculateDailystatus');
}
