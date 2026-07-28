# Master Data Import and Pagination Update

## Scope

This update completes direct UI integration for the reusable master-data import foundation and standard pagination controls.

## Modules

- Cadre Masters
- Bachelor Subjects
- Post-related Subjects

## Listing standard

Each index page provides:

- Search
- 25, 50 or 100 rows per page
- Preserved query strings while navigating
- Total and current-range information
- Add Record
- Download Template
- Import Excel

Unsupported `per_page` values fall back to 25 through `PaginationSettings`.

## Import workflow

1. Download the module template.
2. Upload XLSX, XLS or CSV.
3. Choose Insert, Update or Upsert mode.
4. Validate and preview.
5. Review new, existing and invalid rows.
6. Confirm the import.

No database mutation occurs before confirmation. Confirmed imports run inside a central-database transaction.

## Duplicate safety

- Subjects are matched by `subject_code`.
- Cadres are matched by `cadre_code`.
- Cadre abbreviation conflicts are rejected during preview.
- Duplicate unique values inside the uploaded spreadsheet are rejected during preview.

## Future standard

Large CRUD/reference modules should use the same pagination and preview-before-confirm import pattern unless their business rules require a dedicated pipeline.
