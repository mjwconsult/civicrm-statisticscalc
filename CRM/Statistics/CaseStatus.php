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
ORDER BY cc.id ASC,lcc.modified_date ASC";

    $dao = CRM_Core_DAO::executeQuery($sqlSourceData);

    $caseStatus = ['current' => NULL, 'previous' => NULL];
    $caseID = ['current' => NULL, 'previous' => NULL];
    $count = 0;
    while ($dao->fetch()) {
      $count++;
      if ($count > 1000) {
        return;
      }
      $caseID['previous'] = $caseID['current'];
      $caseStatus['previous'] = $caseID['current'];

      $caseID['current'] = (int) $dao->case_id;
      $caseStatus['current'] = (int) $dao->status_id;

      if ($caseID['current'] !== $caseID['previous']) {
        // New case to record status
        $sql = "INSERT INTO civicrm_statistics_casestatus (case_id,modified_date,status_id,start_date,end_date,label)
                VALUES ({$dao->case_id},{$dao->modified_date},{$dao->status_id},{$dao->start_date},{$dao->end_date},{$dao->label})";
        CRM_Core_DAO::executeQuery($sql);
        continue;
      }
      if ($caseStatus['current'] !== $caseStatus['previous']) {
        if ($caseID['current'] === $caseID['previous']) {
          // New status for current case
          $sql = "INSERT INTO civicrm_statistics_casestatus (case_id,modified_date,status_id,start_date,end_date,label)
                VALUES ('{$dao->case_id}','{$dao->modified_date}','{$dao->status_id}','{$dao->start_date}','{$dao->end_date}','{$dao->label}')";
          CRM_Core_DAO::executeQuery($sql);
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
        `modified_date` timestamp NULL DEFAULT NULL COMMENT 'When was the case (or closely related entity) was created or modified or deleted.',
        `status_id` int(10) unsigned DEFAULT NULL COMMENT 'Id of case status.',
        `start_date` date DEFAULT NULL COMMENT 'Date on which given case starts.',
        `end_date` date DEFAULT NULL COMMENT 'Date on which given case ends.',
        `label` varchar(512) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Option string as displayed to users - e.g. the label in an HTML OPTION tag.'
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
    $startDate = new DateTime($startDate);
    return $startDate;
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
    $currentCaseID = $previousCaseID = 0;

    while ($dao->fetch()) {
      $previousCaseID = $currentCaseID;
      $currentCaseID = (int) $dao->case_id;

      if ($currentCaseID !== $previousCaseID) {
        if ($previousCaseID !== 0) {
          // We are calculating for a new case, record the previous case status
          $statuses = self::updateStatusCounts($statuses, $status, $date, new DateTime($dao->start_date));
        }
        // Start calculations for a new case.
        $status = (int) $dao->status_id;
      }
      else {
        $modifiedDate = new DateTime($dao->modified_date);
        $modifiedDate->setTime(0,0,0);
        // Continue with calculations for existing case.
        if ($modifiedDate < $date) {
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
