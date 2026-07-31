# R4 Enterprise Registration Import

## Purpose

Registration files may contain 300,000+ rows. The HTTP request only stores the file and creates a batch. A database-queue job processes the file in bounded spreadsheet chunks, updates progress after every chunk, preserves row-level audit data, and supports batch rollback.

## Runtime requirements

For a 42.8 MB upload, update the **Apache PHP configuration** used by XAMPP and restart Apache:

```ini
upload_max_filesize = 128M
post_max_size = 128M
memory_limit = 512M
max_execution_time = 120
max_input_time = 120
```

The queue worker is long-running, so its CLI PHP may use a separate `php.ini`. Verify both with `php --ini` and the browser `phpinfo()` page.

## Installation

```bash
php artisan optimize:clear
php artisan examination:migrate
php artisan migrate
php artisan queue:work database --queue=imports --timeout=0 --tries=1
```

Keep the worker terminal open during the import. After changing PHP code, restart it:

```bash
php artisan queue:restart
```

## Environment options

```env
QUEUE_CONNECTION=database
REGISTRATION_IMPORT_QUEUE=imports
REGISTRATION_IMPORT_CHUNK_SIZE=1000
```

Start with 1000 rows per chunk. Lower to 500 if a machine has limited RAM. Do not increase memory merely to load the whole workbook.

## Workflow

1. Upload spreadsheet.
2. Batch status becomes `queued`.
3. Queue worker configures the selected examination database.
4. Header is validated once.
5. Rows are read and committed in bounded chunks.
6. Progress, inserted, updated, failed, warning and conflict counters are updated after each chunk.
7. Final status becomes `completed`, `completed_with_errors`, or `failed`.

University mismatch remains non-blocking: the source code is retained and an import warning is appended to the comment.
