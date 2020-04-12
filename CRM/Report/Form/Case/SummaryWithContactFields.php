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
 * Class CRM_Report_Form_Case_SummaryWithContactFields
 *
 * Based on the CRM_Report_Form_Case_Summary built-in report but with relationships removed and generic contact fields added
 * Contact ID is also exposed.
 */
class CRM_Report_Form_Case_SummaryWithContactFields extends CRM_Report_Form {

  protected $_summary = NULL;
  protected $_exposeContactID = TRUE;

  protected $_customGroupExtends = ['Case'];

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->case_types = CRM_Case_PseudoConstant::caseType();
    $this->case_statuses = CRM_Core_OptionGroup::values('case_status');

    $this->deleted_labels = [
      '' => ts('- select -'),
      0 => ts('No'),
      1 => ts('Yes'),
    ];

    $this->_columns = [
      'civicrm_contact' => [
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => $this->getBasicContactFields(),
        'order_bys' => [
          'client_name' => [
            'title' => ts('Contact Name'),
            'name' => 'sort_name',
          ],
        ],
        'grouping'  => 'case-fields',
        'filters' => CRM_Report_Form::getBasicContactFilters(),
      ],
      'civicrm_case' => [
        'dao' => 'CRM_Case_DAO_Case',
        'fields' => [
          'id' => [
            'title' => ts('Case ID'),
            'required' => TRUE,
          ],
          'subject' => [
            'title' => ts('Case Subject'),
            'default' => TRUE,
          ],
          'status_id' => [
            'title' => ts('Status'),
            'default' => TRUE,
          ],
          'case_type_id' => [
            'title' => ts('Case Type'),
            'default' => TRUE,
          ],
          'start_date' => [
            'title' => ts('Start Date'),
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_DATE,
          ],
          'end_date' => [
            'title' => ts('End Date'),
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_DATE,
          ],
          'duration' => [
            'title' => ts('Duration (Days)'),
            'default' => FALSE,
          ],
          'is_deleted' => [
            'title' => ts('Deleted?'),
            'default' => FALSE,
            'type' => CRM_Utils_Type::T_INT,
          ],
        ],
        'filters' => [
          'start_date' => [
            'title' => ts('Start Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ],
          'end_date' => [
            'title' => ts('End Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ],
          'case_type_id' => [
            'title' => ts('Case Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Case_BAO_Case::buildOptions('case_type_id', 'search'),
          ],
          'status_id' => [
            'title' => ts('Status'),
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Case_BAO_Case::buildOptions('status_id', 'search'),
          ],
          'is_deleted' => [
            'title' => ts('Deleted?'),
            'type' => CRM_Report_Form::OP_INT,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => $this->deleted_labels,
            'default' => 0,
          ],
        ],
        'order_bys'  => [
          'start_date' => [
            'title' => ts('Start Date'),
          ],
          'end_date' => [
            'title' => ts('End Date'),
          ],
          'status_id' => [
            'title' => ts('Status'),
          ],
        ],
        'grouping'  => 'case-fields',
      ],
      'civicrm_case_contact' => [
        'dao' => 'CRM_Case_DAO_CaseContact',
      ],
    ];

    parent::__construct();
  }

  public function preProcess() {
    parent::preProcess();
  }

  public function select() {
    $select = [];
    $this->_columnHeaders = [];
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (!empty($field['required']) ||
            !empty($this->_params['fields'][$fieldName])
          ) {
            if ($fieldName == 'duration') {
              $select[] = "IF({$table['fields']['end_date']['dbAlias']} Is Null, '', DATEDIFF({$table['fields']['end_date']['dbAlias']}, {$table['fields']['start_date']['dbAlias']})) as {$tableName}_{$fieldName}";
            }
            else {
              $select[] = "{$field['dbAlias']} as {$tableName}_{$fieldName}";
            }
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
          }
        }
      }
    }
    $this->_selectClauses = $select;

    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  public function from() {
    $cc = $this->_aliases['civicrm_case'];
    $c = $this->_aliases['civicrm_contact'];
    $ccc = $this->_aliases['civicrm_case_contact'];

    $this->_from = "
            FROM civicrm_case $cc
inner join civicrm_case_contact $ccc on ${ccc}.case_id = ${cc}.id
inner join civicrm_contact $c on ${c}.id=${ccc}.contact_id
";
  }

  public function where() {
    $clauses = [];
    $this->_having = '';
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('filters', $table)) {
        foreach ($table['filters'] as $fieldName => $field) {
          $clause = NULL;
          if (CRM_Utils_Array::value("operatorType", $field) & CRM_Report_Form::OP_DATE
          ) {
            $relative = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params);
            $from = CRM_Utils_Array::value("{$fieldName}_from", $this->_params);
            $to = CRM_Utils_Array::value("{$fieldName}_to", $this->_params);

            $clause = $this->dateClause($field['dbAlias'], $relative, $from, $to,
              CRM_Utils_Array::value('type', $field)
            );
          }
          else {

            $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
            if ($fieldName == 'case_type_id') {
              $value = CRM_Utils_Array::value("{$fieldName}_value", $this->_params);
              if (!empty($value)) {
                $operator = '';
                if ($op == 'notin') {
                  $operator = 'NOT';
                }

                $regexp = "[[:cntrl:]]*" . implode('[[:>:]]*|[[:<:]]*', $value) . "[[:cntrl:]]*";
                $clause = "{$field['dbAlias']} {$operator} REGEXP '{$regexp}'";
              }
              $op = NULL;
            }

            if ($op) {
              $clause = $this->whereClause($field,
                $op,
                CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
              );
            }
          }

          if (!empty($clause)) {
            $clauses[] = $clause;
          }
        }
      }
    }

    if (empty($clauses)) {
      $this->_where = "WHERE ( 1 ) ";
    }
    else {
      $this->_where = "WHERE " . implode(' AND ', $clauses);
    }
  }

  public function groupBy() {
    $this->_groupBy = "";
  }

  public function postProcess() {

    $this->beginPostProcess();

    $sql = $this->buildQuery(TRUE);

    $rows = $graphRows = [];
    $this->buildRows($sql, $rows);

    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  /**
   * Alter display of rows.
   *
   * Iterate through the rows retrieved via SQL and make changes for display purposes,
   * such as rendering contacts as links.
   *
   * @param array $rows
   *   Rows generated by SQL, with an array for each row.
   */
  public function alterDisplay(&$rows) {
    $entryFound = FALSE;
    foreach ($rows as $rowNum => $row) {
      if (array_key_exists('civicrm_case_status_id', $row)) {
        if ($value = $row['civicrm_case_status_id']) {
          $rows[$rowNum]['civicrm_case_status_id'] = $this->case_statuses[$value];
          $entryFound = TRUE;
        }
      }

      if (array_key_exists('civicrm_case_case_type_id', $row) &&
        !empty($rows[$rowNum]['civicrm_case_case_type_id'])
      ) {
        $value = $row['civicrm_case_case_type_id'];
        $typeIds = explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
        $value = [];
        foreach ($typeIds as $typeId) {
          if ($typeId) {
            $value[$typeId] = $this->case_types[$typeId];
          }
        }
        $rows[$rowNum]['civicrm_case_case_type_id'] = implode(', ', $value);
        $entryFound = TRUE;
      }

      // convert Case ID and Subject to links to Manage Case
      if (array_key_exists('civicrm_case_id', $row) &&
        !empty($rows[$rowNum]['civicrm_contact_id'])
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view/case",
          'reset=1&action=view&cid=' . $row['civicrm_contact_id'] . '&id=' .
          $row['civicrm_case_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_case_id_link'] = $url;
        $rows[$rowNum]['civicrm_case_id_hover'] = ts("Manage Case");
        $entryFound = TRUE;
      }
      if (array_key_exists('civicrm_case_subject', $row) &&
        !empty($rows[$rowNum]['civicrm_contact_id'])
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view/case",
          'reset=1&action=view&cid=' . $row['civicrm_contact_id'] . '&id=' .
          $row['civicrm_case_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_case_subject_link'] = $url;
        $rows[$rowNum]['civicrm_case_subject_hover'] = ts("Manage Case");
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_case_is_deleted', $row)) {
        $value = $row['civicrm_case_is_deleted'];
        $rows[$rowNum]['civicrm_case_is_deleted'] = $this->deleted_labels[$value];
        $entryFound = TRUE;
      }

      // make count columns point to detail report
      // convert sort name to links
      if (array_key_exists('civicrm_contact_sort_name', $row) &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        $url = CRM_Report_Utils_Report::getNextUrl('contact/detail',
          'reset=1&force=1&id_op=eq&id_value=' . $row['civicrm_contact_id'],
          $this->_absoluteUrl, $this->_id, $this->_drilldownReport
        );
        $rows[$rowNum]['civicrm_contact_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_sort_name_hover'] = ts('View Contact Detail Report for this contact');
        $entryFound = TRUE;
      }

      // Handle ID to label conversion for contact fields
      $entryFound = $this->alterDisplayContactFields($row, $rows, $rowNum, 'contact/summary', 'View Contact Summary') ? TRUE : $entryFound;

      // display birthday in the configured custom format
      if (array_key_exists('civicrm_contact_birth_date', $row)) {
        $birthDate = $row['civicrm_contact_birth_date'];
        if ($birthDate) {
          $rows[$rowNum]['civicrm_contact_birth_date'] = CRM_Utils_Date::customFormat($birthDate, '%Y%m%d');
        }
        $entryFound = TRUE;
      }

      if (!$entryFound) {
        break;
      }
    }
  }

}
