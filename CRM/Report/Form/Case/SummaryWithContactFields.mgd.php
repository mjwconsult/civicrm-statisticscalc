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
    'name' => 'CRM_Report_Form_Case_SummaryWithContactFields',
    'entity' => 'ReportTemplate',
    'params' =>
    [
      'version' => 3,
      'label' => 'Case Summary with Contact Fields Report',
      'description' => 'Provides a summary of cases with contact and custom fields',
      'class_name' => 'CRM_Report_Form_Case_SummaryWithContactFields',
      'report_url' => 'case/summarywithcontactfields',
      'component' => 'CiviCase',
    ],
  ],
];
