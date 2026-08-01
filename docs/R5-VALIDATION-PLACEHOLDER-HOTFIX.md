# R5 Validation Placeholder Hotfix

## Purpose

Fixes MySQL error 1390 (`Prepared statement contains too many placeholders`) during registration staging validation.

## Changes

- Validation read chunks may remain large for efficient lookups.
- Staging upserts are split into safe write batches, default 2,000 rows.
- Failed validation batches with intact staged rows can be retried without re-uploading the workbook.
- The import result screen shows a **Retry Validation** action for such batches.

## Environment

```env
REGISTRATION_VALIDATION_CHUNK_SIZE=5000
REGISTRATION_VALIDATION_WRITE_CHUNK_SIZE=2000
```

The read chunk controls validation/query batching. The write chunk independently controls prepared-statement size.

## Apply

```bash
php artisan optimize:clear
php artisan queue:restart
php artisan test
```

Then restart the import worker and click **Retry Validation** on the failed batch.
