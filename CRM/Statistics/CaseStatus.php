<?php

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
    $statusEndDate = CRM_Core_DAO::singleValueQuery("SELECT MAX(status_enddate) FROM civicrm_statistics_casestatus");
    if ($statusEndDate) {
      $sqlSourceData .= "AND lcc.modified_date > '{$statusEndDate}' ";
    }

    $sqlSourceData .= "ORDER BY cc.id ASC";

    $dao = CRM_Core_DAO::executeQuery($sqlSourceData);

    $caseStatus = ['current' => NULL, 'previous' => NULL];
    $caseID = ['current' => NULL, 'previous' => NULL];
    $caseModified = ['current' => NULL, 'previous' => NULL];
    while ($dao->fetch()) {
      $caseID['previous'] = $caseID['current'];
      $caseStatus['previous'] = $caseStatus['current'];
      $caseModified['previous'] = $caseModified['current'];

      $caseID['current'] = (int) $dao->case_id;
      $caseStatus['current'] = (int) $dao->status_id;
      $caseModified['current'] = $dao->modified_date;

      $queryParams = [
        1 => [$dao->case_id, 'Integer'],
        2 => [CRM_Utils_Date::isoToMysql($caseModified['previous']), 'Timestamp'],
        3 => [CRM_Utils_Date::isoToMysql($caseModified['current']), 'Timestamp'],
        4 => [$dao->status_id, 'Integer'],
        5 => [CRM_Utils_Date::isoToMysql($dao->start_date), 'Date'],
        6 => [CRM_Utils_Date::isoToMysql($dao->end_date), 'Date'],
        7 => [$dao->label, 'String'],
      ];
      if ($caseID['current'] !== $caseID['previous']) {
        $caseModified['current'] = $dao->start_date;
        $queryParams[2] = [CRM_Utils_Date::isoToMysql($caseModified['current']), 'Timestamp'];
        // New case to record status
        $sql = "INSERT INTO civicrm_statistics_casestatus (case_id,status_startdate,status_enddate,status_id,start_date,end_date,label)
                VALUES (%1, %2, %3, %4, %5, %6, %7)";
        CRM_Core_DAO::executeQuery($sql, $queryParams);
        continue;
      }
      if ($caseStatus['current'] !== $caseStatus['previous']) {
        if ($caseID['current'] === $caseID['previous']) {
          // New status for current case
          $sql = "INSERT INTO civicrm_statistics_casestatus (case_id,status_startdate,status_enddate,status_id,start_date,end_date,label)
                  VALUES (%1, %2, %3, %4, %5, %6, %7)";
          CRM_Core_DAO::executeQuery($sql, $queryParams);
          continue;
        }
      }

    }
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

  public static function getStartDateForStatusCounts(): ?DateTime {
    $startDate = CRM_Core_DAO::singleValueQuery("SELECT min(start_date) FROM civicrm_statistics_casestatus");
    if (!empty($startDate)) {
      return new DateTime($startDate);
    }
    return NULL;
  }

  public static function getLastDateForStatusCounts(): ?DateTime {
    $date = CRM_Core_DAO::singleValueQuery("SELECT MAX(date) FROM civicrm_statistics_casestatus_daily");
    if (!empty($date)) {
      return new DateTime($date);
    }
    return NULL;
  }

  public static function updateStatusCountsForDate(DateTime $date): array {
    // Initialise counts
    $caseStatusesSql = "SELECT value FROM civicrm_option_value WHERE option_group_id = (SELECT id FROM civicrm_option_group WHERE name='case_status')";
    $dao = CRM_Core_DAO::executeQuery($caseStatusesSql);
    while ($dao->fetch()) {
      $statuses[$dao->value] = 0;
    }

    $sql = "SELECT * FROM civicrm_statistics_casestatus";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $currentCaseID = $previousCaseID = $status = NULL;

    while ($dao->fetch()) {
      $previousCaseID = $currentCaseID;
      $currentCaseID = (int) $dao->case_id;

      if ($currentCaseID !== $previousCaseID) {
        if ($previousCaseID !== NULL) {
          // We are calculating for a new case, record the previous case status
          $statuses = self::updateStatusCounts($statuses, $status, $date, new DateTime($dao->start_date));
        }
        // Start calculations for a new case.
        $status = (int) $dao->status_id;
      }
      else {
        $startDateForStatus = new DateTime($dao->status_startdate);
        $startDateForStatus->setTime(0,0,0);
        // Check if the start date for this status was BEFORE the date we are calculating for.
        // This check will run for every record and end up with the LATEST status for the date
        if ($startDateForStatus < $date) {
          $status = $dao->status_id;
        }
      }
    }
    $statuses = self::updateStatusCounts($statuses, $status, $date, new DateTime($dao->start_date));

    $sqlDelete = "DELETE FROM civicrm_statistics_casestatus_daily WHERE date=" . $date->format('Y-m-d');
    CRM_Core_DAO::executeQuery($sqlDelete);
    $sqlInsert = "INSERT INTO civicrm_statistics_casestatus_daily (date, status_" . implode(', status_', array_keys($statuses)) . ") ";
    $sqlInsert .= "VALUES ('" . $date->format('Y-m-d') . "', " . implode(',', $statuses) . ")";
    CRM_Core_DAO::executeQuery($sqlInsert);
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
