# Install R4 Registration Import

This package already includes the earlier R3 UI/Master Import update and both master-import hotfixes. Merge the contents of `bcs-result-processing-master/` into the Laravel project root.

## 1. Backup

```bash
git add .
git commit -m "chore: checkpoint before R4 registration importer"
```

## 2. Configure PHP upload limits

Edit the `php.ini` loaded by Apache, then restart Apache:

```ini
upload_max_filesize = 128M
post_max_size = 128M
memory_limit = 512M
max_execution_time = 120
max_input_time = 120
```

Confirm the browser/Apache PHP configuration with `phpinfo()`. Confirm CLI PHP with:

```bash
php --ini
```

## 3. Configure queue

In `.env`:

```env
QUEUE_CONNECTION=database
REGISTRATION_IMPORT_QUEUE=imports
REGISTRATION_IMPORT_CHUNK_SIZE=1000
```

## 4. Run migrations and tests

```bash
php artisan optimize:clear
php artisan migrate
php artisan examination:migrate
php artisan test
```

## 5. Start the import worker

Open a separate CMD window in the project root:

```bash
php artisan queue:work database --queue=imports --timeout=0 --tries=1
```

Keep this window open during the import. After future code changes run:

```bash
php artisan queue:restart
```

## 6. Import

Open `Registrations > Import`, upload the file, and click **Queue Import**. The result page polls every two seconds and shows chunk-level progress.

For the 42.8 MB / 374,749-row file, begin with chunk size 1000. If memory remains tight, set `REGISTRATION_IMPORT_CHUNK_SIZE=500`, clear configuration, and restart the worker.
