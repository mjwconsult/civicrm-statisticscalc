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

require_once 'statisticscalc.civix.php';
use CRM_Statisticscalc_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function statisticscalc_civicrm_config(&$config) {
  if (isset(Civi::$statics[__FUNCTION__])) {
    return;
  }
  Civi::$statics[__FUNCTION__] = 1;
  Civi::dispatcher()->addListener('hook_civicrm_postCommit', 'CRM_Statistics_ActivityNumericalScores::callbackPostCalculateActivityScores');
  Civi::dispatcher()->addListener('hook_civicrm_caseChange', 'CRM_Statistics_CaseStatistics::hookCaseChangeCalculateScores');
  _statisticscalc_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function statisticscalc_civicrm_install() {
  _statisticscalc_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function statisticscalc_civicrm_postInstall() {
  _statisticscalc_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function statisticscalc_civicrm_uninstall() {
  _statisticscalc_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function statisticscalc_civicrm_enable() {
  _statisticscalc_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function statisticscalc_civicrm_disable() {
  _statisticscalc_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function statisticscalc_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _statisticscalc_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function statisticscalc_civicrm_entityTypes(&$entityTypes) {
  _statisticscalc_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_alterLogTables().
 *
 * Exclude firewall tables from logging tables since they hold mostly temp data.
 */
function statisticscalc_civicrm_alterLogTables(&$logTableSpec) {
  $tablePrefix = 'civicrm_statistics_';
  $len = strlen($tablePrefix);

  foreach ($logTableSpec as $key => $val) {
    if (substr($key, 0, $len) === $tablePrefix) {
      unset($logTableSpec[$key]);
    }
  }
}
