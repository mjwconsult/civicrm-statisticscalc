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

class CRM_Statistics_Utils_Hook {
  static $_nullObject = NULL;

  /**
   * This hook is called to get a list of extensions implementing statistics
   *
   * @param array $metadata
   *   ['activity'] => [
   *     'class' => 'CRM_Wehearyou_Statistics',
   *     'method' => 'getActivityCalculationMetadata',
   *     'label' => E::ts('PHQ / GAD scores'),
   *   ];
   *
   * @return mixed
   *   based on op. pre-hooks return a boolean or
   *   an error message which aborts the operation
   */
  public static function getStatisticsMetadata(&$metadata) {
    $hook = CRM_Utils_Hook::singleton();
    return $hook->invoke(1, $metadata, self::$_nullObject, self::$_nullObject, self::$_nullObject, self::$_nullObject, self::$_nullObject, 'civicrm_statistics_metadata');
  }

}
