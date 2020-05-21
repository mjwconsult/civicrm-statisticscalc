# Statistics Calculator

* It provides infrastructure for calculating statistics:
  * Calculates scores for certain activity types.
  * Generates a set of "Case Statistics" into custom fields that can be used for reporting via "Case Reports".
* Provides a case summary report with contact fields.

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v7.2+
* CiviCRM 5.24+

## Usage

This extension does nothing by itself.

You have to implement the hook:
```php
/**
 * Implements hook_civicrm_statistics_metadata()
 */
function thesite_civicrm_statistics_metadata(&$metadata) {
  $metadata['activity'][] = [
    'class' => 'CRM_TheSite_StatisticsMetadata',
    'method' => 'getActivityCalculationMetadata',
  ];
  $metadata['case'][] = [
    'class' => 'CRM_TheSite_StatisticsMetadata',
    'method' => 'getCaseCalculationMetadata',
  ];
}
```

And then the actual functions to define the metadata:

##### For cases:
```php
  /**
   * Generate case statistics for a single case and save to case custom fields
   *
   * @return array
   */
  public static function getCaseCalculationMetadata() {
    $activityTypesCount = [
      'activityTypes' => [
        'Assessment Notes' => [
          'Case_Statistics.Assessment_Completed_' => 'Completed',
          'Case_Statistics.Assessment_Cancelled_' => 'Cancelled',
        ],
        'Session' => [
          'Case_Statistics.Sessions_Completed_' => 'Completed',
          'Case_Statistics.Sessions_Cancelled_' => 'Cancelled',
        ]
      ],
    ];

    // This copies values from an activity custom field to a case custom field
    $activityTypesField = [
      /*
       * activityTypeName => [
       *   caseCustomFieldName => activityCustomField
       * ]
       */
      'Pre-Assessment' => [
        'Case_Statistics.PHQ_Pre_assessment_' => 'custom_165',
        'Case_Statistics.GAD_Pre_assessment_' => 'custom_166',
      ],
      'Client Evaluation' => [
        'Case_Statistics.PHQ_Client_Evaluation_' => 'custom_161',
        'Case_Statistics.GAD_Client_Evaluation_' => 'custom_162',
      ]
    ];

    return [
      'activityTypesCount' => $activityTypesCount,
      'activityTypesCopyField' => $activityTypesField,
    ];
  }
```

##### For activities:
```php
  /**
   * This is the metadata used to specify how calculations are performed.
   * Eventually we might move this to a user configurable / settings based system.
   * In this example two of the source fields are case fields from the case the activity is linked to (case.xx)
   *
   * @return array
   */
  public static function getActivityCalculationMetadata() {
      $activityTypesCalculations['Pre-Assessment'][] = [
      'mode' => CRM_Statistics_ActivityNumericalScores::CALC_MODE_INT_SUM,
      'name' => 'PHQ - Pre-Assessment',
      'sourceFields' => [
        'custom_63',
        'custom_64',
        'custom_65',
        'custom_66',
        'custom_67',
        'custom_68',
        'custom_69',
        'custom_70',
        'custom_71'
      ],
      'resultEntity' => 'activity',
      'resultField' => 'custom_165',
    ];
    $activityTypesCalculations['Pre-Assessment'][] = [
      'mode' => CRM_Statistics_ActivityNumericalScores::CALC_MODE_INT_SUM,
      'name' => 'GAD - Pre-Assessment',
      'sourceFields' => [
        'custom_152',
        'custom_153',
        'custom_154',
        'custom_155',
        'custom_156',
        'custom_157',
        'custom_158'
      ],
      'resultEntity' => 'activity',
      'resultField' => 'custom_166',
    ];

    // Standard custom fields for PHQ/GAD
    $phq = [
      'mode' => CRM_Statistics_ActivityNumericalScores::CALC_MODE_INT_SUM,
      'sourceFields' => [
        'case.custom_74',
        'custom_75',
        'custom_76',
        'custom_77',
        'custom_78',
        'custom_79',
        'custom_80',
        'custom_81',
        'custom_82'
      ],
      'resultEntity' => 'activity',
      'resultField' => 'custom_161',
    ];

    $gad = [
      'mode' => CRM_Statistics_ActivityNumericalScores::CALC_MODE_INT_SUM,
      'sourceFields' => [
        'case.custom_83',
        'custom_84',
        'custom_85',
        'custom_86',
        'custom_87',
        'custom_88',
        'custom_89'
      ],
      'resultEntity' => 'activity',
      'resultField' => 'custom_162',
    ];

    // Label Client Evaluation
    $phq['name'] = 'PHQ - Client Evaluation';
    $activityTypesCalculations['Client Evaluation'][] = $phq;
    $gad['name'] = 'GAD - Client Evaluation';
    $activityTypesCalculations['Client Evaluation'][] = $gad;

    return $activityTypesCalculations;
  }
```
