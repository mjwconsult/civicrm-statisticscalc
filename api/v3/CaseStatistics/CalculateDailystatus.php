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
function _civicrm_api3_case_statistics_calculate_dailystatus_spec(&$spec) {
  $spec['date']['api.required'] = 0;
  $spec['date']['title'] = 'The date to calculate for';
  $spec['date']['description'] = 'If not specified, status counts will be calculated for the current day';
  $spec['date']['type'] = CRM_Utils_Type::T_DATE;
  $spec['backfill']['title'] = 'Recalculate all dates';
  $spec['backfill']['type'] = CRM_Utils_Type::T_BOOLEAN;
  $spec['generate_source_data']['title'] = 'Generate source data';
  $spec['generate_source_data']['type'] = CRM_Utils_Type::T_BOOLEAN;
  $spec['generate_source_data']['api.default'] = TRUE;
  $spec['calculate_daily_counts']['title'] = 'Calculate daily counts data';
  $spec['calculate_daily_counts']['type'] = CRM_Utils_Type::T_BOOLEAN;
  $spec['calculate_daily_counts']['api.default'] = TRUE;
}

/**
 * CaseStatistics.Calculate_Scores API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_case_statistics_calculate_dailystatus($params) {
  if ($params['generate_source_data']) {
    CRM_Statistics_CaseStatus::createTargetTable();
    CRM_Statistics_CaseStatus::createSourceTable();
    CRM_Statistics_CaseStatus::createSourceDataForStatusCounts();
  }

  if (!$params['calculate_daily_counts']) {
    return civicrm_api3_create_success([], $params, 'CaseStatistics', 'CalculateDailystatus');
  }

  $startDate = NULL;
  if (!empty($params['date'])) {
    $lastDate = new DateTime($params['date']);
    $startDate = clone $lastDate;
  }
  else {
    $lastDate = new DateTime('now');
    // By default if no params are specified we calculate all missing days from the last day that was calculated.
    $startDate = CRM_Statistics_CaseStatus::getLastDateForStatusCounts();
    if (!$startDate) {
      $startDate = CRM_Statistics_CaseStatus::getStartDateForStatusCounts();
    }
    $startDate->modify('+1 day');
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
