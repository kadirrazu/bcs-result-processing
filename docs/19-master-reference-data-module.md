# Master Reference Data Module

## Status
Architecture and implementation baseline for Milestone 4A.

## Placement
All three tables live only in the CENTRAL database:

- `cadre_masters`
- `bachelor_subjects`
- `post_related_subjects`

They do not carry `examination_id`. Examination-specific modules will copy selected cadre master values into each physical examination database as immutable-by-default snapshots.

## Locked rules

- `cadre_code` is an integer and unique.
- `cadre_abbr` is unique and normalized to uppercase.
- `cadre_title_bn` is mandatory for Bangla result and report output.
- `cadre_type` is `GG` or `TT`.
- Both subject master tables store only code, name and active status.
- `subject_code` is a string and unique in its own table. String storage preserves leading zero and supports alphanumeric official codes.
- Post-related subject marks and pass thresholds do not belong to these master tables. The fixed 200-mark rule remains written-examination business logic.
- Master records are not hard-deleted. Lifecycle is controlled through `is_active`.

## Future integration

Milestone 4B will import selected active cadre masters into the selected examination database, copy all reporting fields as a historical snapshot, prevent duplicates and create/manage matching post records.
