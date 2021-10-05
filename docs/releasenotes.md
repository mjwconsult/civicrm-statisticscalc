## Information

Releases use the following numbering system:
**{major}.{minor}.{incremental}**

* major: Major refactoring or rewrite - make sure you read and test very carefully!
* minor: Breaking change in some circumstances, or a new feature. Read carefully and make sure you understand the impact of the change.
* incremental: A "safe" change / improvement. Should *always* be safe to upgrade.

* **[BC]**: Items marked with [BC] indicate a breaking change that will require updates to your code if you are using that code in your extension.

## Release 1.7

* Add "Case Detail with extra fields" report (adds full address + comms prefs fields).

## Release 1.6

* Regenerate civix for PHP7.4 compatibility.
* Support contact custom fields in case report.

## Release 1.5

* Add parameters to calculatedailystatus job.
* Update hook definition.
* Fix overwriting of result fields with NULL if multiple activities are defined with the same result field.

## Release 1.4

* Exclude `civicrm_statistics_*` tables from logging.

## Release 1.3

* Fix activity calculations with case source fields.

## Release 1.2

* Fix issue where case statistics were not reset to NULL if no value was found.

## Release 1.1

* Add hook_civicrm_post and hook_civicrm_caseChange listeners for activity/case score calculations
* Catch and continue if activity types don't exist so the rest of the calculations will work, useful for test sites etc

## Release 1.0

Initial release.
