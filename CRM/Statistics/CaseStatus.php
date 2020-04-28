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

class CRM_Statistics_CaseStatus {

  public static function createSourceDataForStatusCounts() {
    /*
    $sqlSourceData = "
CREATE TABLE civicrm_statistics_casestatus AS
SELECT cc.id as case_id,lcc.modified_date,lcc.status_id,cc.start_date,cc.end_date,cov.label,ccc.contact_id,ccon.display_name FROM civicrm_case cc
INNER JOIN log_civicrm_case lcc ON cc.id = lcc.id
LEFT JOIN
(SELECT value,label FROM civicrm_option_value WHERE option_group_id = (SELECT id FROM civicrm_option_group WHERE name='case_status')) as cov
ON cov.value = lcc.status_id
LEFT JOIN civicrm_case_contact ccc ON cc.id = ccc.case_id
LEFT JOIN civicrm_contact ccon ON ccc.contact_id = ccon.id
GROUP BY lcc.id,lcc.status_id ORDER BY cc.id ASC,lcc.modified_date ASC";
    */

    $sqlSourceData = "SELECT cc.id as case_id,lcc.modified_date,lcc.status_id,cc.start_date,cc.end_date,cov.label,ccc.contact_id,ccon.display_name FROM civicrm_case cc
INNER JOIN log_civicrm_case lcc ON cc.id = lcc.id
LEFT JOIN
    (SELECT value,label FROM civicrm_option_value WHERE option_group_id = (SELECT id FROM civicrm_option_group WHERE name='case_status')) as cov
ON cov.value = lcc.status_id
LEFT JOIN civicrm_case_contact ccc ON cc.id = ccc.case_id
LEFT JOIN civicrm_contact ccon ON ccc.contact_id = ccon.id
WHERE ccon.is_deleted = 0 AND cc.is_deleted = 0 ";

    // For performance, each time this query is run, only process new log entries (if you need to reprocess, truncate the table first).
    /*$statusEndDate = CRM_Core_DAO::singleValueQuery("SELECT MAX(status_enddate) FROM civicrm_statistics_casestatus");
    if ($statusEndDate) {
      $sqlSourceData .= "AND lcc.modified_date > '{$statusEndDate}' ";
    }*/
    CRM_Core_DAO::executeQuery('TRUNCATE TABLE civicrm_statistics_casestatus');

    $sqlSourceData .= "ORDER BY cc.id ASC";

    $dao = CRM_Core_DAO::executeQuery($sqlSourceData);

    $caseStatus = ['current' => NULL, 'previous' => NULL];
    $caseID = ['current' => NULL, 'previous' => NULL];
    $caseModified = ['current' => NULL, 'previous' => NULL];
    $caseStatusLabel = ['current' => NULL, 'previous' => NULL];
    $caseChanged = FALSE;
    while ($dao->fetch()) {
      $caseID['previous'] = $caseID['current'];
      $caseStatus['previous'] = $caseStatus['current'];
      $caseModified['previous'] = $caseModified['current'];
      $caseStatusLabel['previous'] = $caseStatusLabel['current'];

      $caseID['current'] = (int) $dao->case_id;
      $caseStatus['current'] = (int) $dao->status_id;
      $caseModified['current'] = $dao->modified_date;
      $caseStatusLabel['current'] = $dao->label;

      \Civi::log()->debug("case_id: {$caseID['previous']}:{$caseID['current']} stat: {$caseStatus['previous']}:{$caseStatus['current']} mod: {$caseModified['previous']}:{$caseModified['current']}");

      if ($caseID['current'] !== $caseID['previous']) {
        \Civi::log()->debug('casechanged');
        if ($caseID['previous']) {
          // Record last status of previous case
          \Civi::log()->debug('recordpreviousstatus');
          self::insertIntoCasestatus($caseID['previous'], $statusStartDate, NULL, $caseStatus['previous'], NULL, NULL, $caseStatusLabel['previous']);
        }
        $statusStartDate = $dao->start_date;
        // The "first" status change should only be recorded once it changes
        $caseStatus['previous'] = $caseStatus['current'];
      }
      if (($caseStatus['current'] !== $caseStatus['previous'])) {
        \Civi::log()->debug('statuschanged');
        // New status for current case
        self::insertIntoCasestatus($dao->case_id, $statusStartDate, $caseModified['current'], $caseStatus['previous'] ?? $caseStatus['current'], $dao->start_date, $dao->end_date, $caseStatusLabel['previous'] ?? $caseStatusLabel['current']);
        $statusStartDate = $caseModified['current'];
        continue;
      }
    }
    if (!empty($caseID['current'])) {
      // Record the final record
      self::insertIntoCasestatus($caseID['current'], $statusStartDate, NULL, $caseStatus['current'], NULL, NULL, $caseStatusLabel['current']);
    }
  }

  private static function insertIntoCasestatus($caseID, $statusStartDate, $statusEndDate, $statusID, $startDate, $endDate, $label) {
    $queryParams = [
      1 => [$caseID, 'Integer'],
      2 => [CRM_Utils_Date::isoToMysql($statusStartDate), 'Timestamp'],
      3 => [CRM_Utils_Date::isoToMysql($statusEndDate), 'Timestamp'],
      4 => [$statusID, 'Integer'],
      5 => [CRM_Utils_Date::isoToMysql($startDate), 'Date'],
      6 => [CRM_Utils_Date::isoToMysql($endDate), 'Date'],
      7 => [$label, 'String'],
    ];
    $sql = "INSERT INTO civicrm_statistics_casestatus (case_id,status_startdate,status_enddate,status_id,start_date,end_date,label)
                  VALUES (%1, %2, %3, %4, %5, %6, %7)";
    CRM_Core_DAO::executeQuery($sql, $queryParams);
  }

  public static function createSourceTable() {
    if (CRM_Core_DAO::checkTableExists('civicrm_statistics_casestatus')) {
      return;
    }
    $sqlSourceCreate = "CREATE TABLE `civicrm_statistics_casestatus` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `case_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Unique Case ID',
        `status_startdate` timestamp NULL DEFAULT NULL COMMENT 'When was the case (or closely related entity) was created or modified or deleted.',
        `status_enddate` timestamp NULL DEFAULT NULL COMMENT 'When was the case (or closely related entity) was created or modified or deleted.',
        `status_id` int(10) unsigned DEFAULT NULL COMMENT 'Id of case status.',
        `start_date` date DEFAULT NULL COMMENT 'Date on which given case starts.',
        `end_date` date DEFAULT NULL COMMENT 'Date on which given case ends.',
        `label` varchar(512) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Option string as displayed to users - e.g. the label in an HTML OPTION tag.',
        PRIMARY KEY (`id`)
    )";

    CRM_Core_DAO::executeQuery($sqlSourceCreate);
  }

  public static function createTargetTable() {
    if (CRM_Core_DAO::checkTableExists('civicrm_statistics_casestatus_daily')) {
      return;
    }
    $sql = "CREATE TABLE `civicrm_statistics_casestatus_daily` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `date` date NULL DEFAULT NULL,";

    $caseStatusesSql = "SELECT value FROM civicrm_option_value WHERE option_group_id = (SELECT id FROM civicrm_option_group WHERE name='case_status')";
    $dao = CRM_Core_DAO::executeQuery($caseStatusesSql);
    while ($dao->fetch()) {
      $sql .= "`status_{$dao->value}` int(10) unsigned DEFAULT NULL,";
    }
    $sql .= "PRIMARY KEY (`id`) )";
    CRM_Core_DAO::executeQuery($sql);
  }

  public static function getStartDateForStatusCounts(): DateTime {
    $startDate = CRM_Core_DAO::singleValueQuery("SELECT min(start_date) FROM civicrm_statistics_casestatus");
    if (!empty($startDate)) {
      return new DateTime($startDate);
    }
    else {
      $startDate = CRM_Core_DAO::singleValueQuery("SELECT min(start_date) FROM civicrm_case");
    }
    return new DateTime($startDate);
  }

  public static function getLastDateForStatusCounts(): ?DateTime {
    $date = CRM_Core_DAO::singleValueQuery("SELECT MAX(date) FROM civicrm_statistics_casestatus_daily");
    if (!empty($date)) {
      return new DateTime($date);
    }
    return NULL;
  }

  public static function updateStatusCountsForDate(DateTime $date): array {
    // Get all case statuses and initialise counts
    $caseStatusesSql = "SELECT value FROM civicrm_option_value WHERE option_group_id = (SELECT id FROM civicrm_option_group WHERE name='case_status')";
    $dao = CRM_Core_DAO::executeQuery($caseStatusesSql);
    while ($dao->fetch()) {
      $statuses[$dao->value] = 0;
    }

    $sql = '
SELECT
	COUNT(*)
FROM
	civicrm_statistics_casestatus
WHERE
	status_id = %1
	AND status_startdate < %2
	AND (status_enddate > %3
	OR status_enddate IS NULL)';

    $queryParams = [
      2 => [$date->setTime(0,0,0)->format('YmdHis'), 'Timestamp'],
      3 => [$date->setTime(0,0,0)->format('YmdHis'), 'Timestamp'],
    ];
    foreach ($statuses as $statusID => $statusCount) {
      $queryParams[1] = [$statusID, 'Integer'];
      $statuses[$statusID] = CRM_Core_DAO::singleValueQuery($sql, $queryParams);
    }

    $sqlDelete = "DELETE FROM civicrm_statistics_casestatus_daily WHERE date=%1";
    $sqlQueryParams = [ 1 => [$date->format('Ymd'), 'Date']];
    CRM_Core_DAO::executeQuery($sqlDelete, $sqlQueryParams);
    $sqlInsert = "INSERT INTO civicrm_statistics_casestatus_daily (date, status_" . implode(', status_', array_keys($statuses)) . ") ";
    $sqlInsert .= "VALUES (%1, " . implode(',', $statuses) . ")";
    CRM_Core_DAO::executeQuery($sqlInsert, $sqlQueryParams);
    return $statuses;
  }

  private static function updateStatusCounts(array $counts, int $status, DateTime $calculationDate, DateTime $caseStartDate) {
    if ($caseStartDate <= $calculationDate) {
      // But only if the start_date of the previous case was before the date we are calculating stats for
      $counts[$status]++;
    }
    return $counts;
  }

}
