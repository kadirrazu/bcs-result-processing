# Registration Module — R2 CRUD and Review

## Scope

R2 completes manual registration maintenance and high-volume server-side review. Excel import remains the R3 responsibility.

## Delivered

- Create, view and update registration records in the active examination database.
- Central-master dropdowns for sex, division, district, university, bachelor subject and post-related subject.
- Unique validation for `user_id` and `reg` on the `exam` connection.
- Normalization of blank optional values to `NULL`.
- Centralized `has_quota` calculation through `RegistrationQuotaResolver`.
- Search and indexed filters without loading the complete candidate dataset.
- Policy-aware action buttons and detailed candidate review page.

## Business rules

- Cadre category remains numeric: `1=GG`, `2=TT`, `3=GT`.
- Raw quota values are preserved exactly as numeric nullable values.
- `has_quota` is true when FF code is `2`, or EM/PHC is non-null.
- Manual saves receive `validation_status=valid` after request validation.
- Master records are read from the central database; examination rows store stable codes.

## Performance

The list uses database pagination and a narrow select list. Identity searches prefer exact indexed values; candidate-name search is the only wildcard fallback.
