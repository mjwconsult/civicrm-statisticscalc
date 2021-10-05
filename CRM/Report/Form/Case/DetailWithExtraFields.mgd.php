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

// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed"
return [
  0 =>
  [
    'name' => 'CRM_Report_Form_Case_DetailWithExtraFields',
    'entity' => 'ReportTemplate',
    'params' =>
    [
      'version' => 3,
      'label' => 'Case Detail with extra fields',
      'description' => 'Provides a summary of cases with contact, custom fields, communications preferences and address fields',
      'class_name' => 'CRM_Report_Form_Case_DetailWithExtraFields',
      'report_url' => 'case/detailwithextrafields',
      'component' => 'CiviCase',
    ],
  ],
];
