<?php

return [
  0 =>
    [
      'name' => 'Calculate Daily Case Status Totals',
      'entity' => 'Job',
      'params' =>
        [
          'version' => 3,
          'name' => 'CalculateDailystatus',
          'description' => 'Calculate the daily totals for case status and output to civicrm_statistics_casestatus_daily table',
          'run_frequency' => 'Daily',
          'api_entity' => 'CaseStatistics',
          'api_action' => 'calculate_dailystatus',
          'parameters' => '',
        ],
    ],
];
