# R5 Registration Staging Import — Paste and Replace

## Verified baseline

The supplied project already uses OpenSpout and a single forward-only XLSX reader. Its remaining cost comes from performing normalization, master validation, identity lookup, main-table upsert, post-upsert lookup, and row-level audit insertion during the same pass. The R5 patch separates that work into three phases:

1. **Stage** — stream XLSX/CSV into `registration_import_staging` with minimal transformation.
2. **Validate** — validate staged rows in 5,000-row chunks and preserve invalid rows for reporting.
3. **Approve/Merge** — merge only `valid` and `warning` rows into `registrations`.

`REG` and `USER` are strict: duplicates inside the batch and conflicts with existing registrations are invalid and cannot be merged.

`B_DATE` is expected as `DDMMYYYY`; staging preserves the raw value and validation later converts only valid dates to `YYYY-MM-DD`.

## Files to paste/replace

Copy the contents of this patch into the Laravel project root and allow replacement.

New files:

- `app/Jobs/ValidateRegistrationImport.php`
- `app/Jobs/ApproveRegistrationImport.php`
- `app/Models/RegistrationImportStaging.php`
- `app/Services/Registrations/RegistrationStagingValidationService.php`
- `app/Services/Registrations/RegistrationApprovalService.php`
- `database/examination-migrations/2026_07_31_200000_create_registration_import_staging.php`

Replaced files:

- `config/registrations.php`
- `app/Models/RegistrationImportBatch.php`
- `app/Services/Registrations/RegistrationImportService.php`
- `app/Services/Registrations/RegistrationImportRollbackService.php`
- `app/Http/Controllers/RegistrationImportController.php`
- `routes/registrations.php`
- `resources/views/registrations/import-result.blade.php`

## Environment

Add or update:

```env
REGISTRATION_IMPORT_QUEUE=imports
REGISTRATION_STAGING_CHUNK_SIZE=2000
REGISTRATION_VALIDATION_CHUNK_SIZE=5000
REGISTRATION_MERGE_CHUNK_SIZE=2000
```

The staging and merge sizes are deliberately capped around 2,000 because a very wide multi-row insert can exceed MySQL's prepared-statement placeholder limit.

## Commands

Stop the worker, paste the files, then run:

```bash
php artisan optimize:clear
php artisan examination:migrate 47
php artisan queue:restart
php artisan queue:work database --queue=imports --timeout=0 --tries=1 --memory=900 -vvv
```

Use the actual examination number when it is not 47.

## Workflow

1. Upload the registration file.
2. Wait for status `staged`.
3. Click **Validate Staged Data**.
4. Review/download the report.
5. Click **Approve & Merge**.
6. Invalid rows remain in staging and are never copied into `registrations`.

## Benchmarking

Measure each phase separately. The 5–50 minute target is a performance objective, not a guarantee; actual time depends on XLSX compression, disk, MySQL durability settings, antivirus scanning, and update-vs-insert ratio. Do not change MySQL durability settings merely for a benchmark on production data.
