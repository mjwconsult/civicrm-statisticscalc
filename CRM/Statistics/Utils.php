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

class CRM_Statistics_Utils {

  /**
   * Return the field ID for $fieldName custom field
   *
   * @param $fieldName
   * @param $fieldGroup
   * @param bool $fullString
   *
   * @return mixed
   * @throws \CRM_Core_Exception
   */
  public static function getCustomByName($fieldName, $fieldGroup, $fullString = TRUE) {
    if (!isset(Civi::$statics[__CLASS__][$fieldGroup][$fieldName])) {
      $field = civicrm_api3('CustomField', 'get', [
        'custom_group_id' => $fieldGroup,
        'name' => $fieldName,
      ]);

      if (!empty($field['id'])) {
        Civi::$statics[__CLASS__][$fieldGroup][$fieldName]['id'] = $field['id'];
        Civi::$statics[__CLASS__][$fieldGroup][$fieldName]['string'] = 'custom_' . $field['id'];
      }
    }

    if ($fullString) {
      return Civi::$statics[__CLASS__][$fieldGroup][$fieldName]['string'];
    }
    return Civi::$statics[__CLASS__][$fieldGroup][$fieldName]['id'];
  }

}
