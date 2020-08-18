## Information

Releases use the following numbering system:
**{major}.{minor}.{incremental}**

Where:

* major: Major refactoring or rewrite - make sure you read and test very carefully!
* minor: Breaking change in some circumstances, or a new feature. Read carefully and make sure you understand the impact of the change.
* incremental: A "safe" change / improvement. Should *always* be safe to upgrade.

## Release 1.3

* Fix activity calculations with case source fields.

## Release 1.2

* Fix issue where case statistics were not reset to NULL if no value was found.

## Release 1.1

* Add hook_civicrm_post and hook_civicrm_caseChange listeners for activity/case score calculations
* Catch and continue if activity types don't exist so the rest of the calculations will work, useful for test sites etc

## Release 1.0

Initial release.
