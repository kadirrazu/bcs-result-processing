# R5 Registration CSV Report Hotfix

## Purpose

Makes the registration import report downloadable for very large batches without hydrating hundreds of thousands of Eloquent models.

## Changes

- Selects only report columns through the examination Query Builder.
- Streams rows through `lazyById(5000)`.
- Safely decodes validation warning/error JSON.
- Writes a UTF-8 BOM for Bangla/Excel compatibility.
- Flushes output every 5,000 rows.
- Disables response buffering where supported.

## Installation

Paste and replace the included `app` folder, then run:

```bash
php artisan optimize:clear
php artisan test
```

No migration or queue restart is required for the download route, although restarting long-running workers after any deployment remains good practice.
