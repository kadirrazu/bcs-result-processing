# Security & Testing Foundation v1

Copy all folders in this package into the Laravel project root and approve replacement of existing files.

Then run:

```bash
composer dump-autoload
php artisan optimize:clear
vendor/bin/pint --dirty
php artisan test
```

Expected result: all tests pass.

Do not commit until the test output has been reviewed.
