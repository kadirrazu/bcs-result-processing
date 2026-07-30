# Registration R3 Installation

1. Back up the active examination database.
2. Replace the project files with this package or review/merge them in the R3 feature branch.
3. Run:

```bash
composer install
php artisan optimize:clear
php artisan examination:migrate
php artisan test
```

The examination migration renames `related_subject_code` to `post_related_subject_code`, adds import batch statistics and creates row-level audit snapshots.

## Important

- Use the newly downloaded R3 Excel template. The old `rl_subject` header is intentionally no longer accepted.
- A rollback restores updated records and deletes records inserted by that batch.
- Take a database backup before applying the migration in a production-like environment.


## R3 UI update

Run `php artisan optimize:clear` after replacing the files. Download fresh registration and master templates from the UI. The registration template no longer includes a division column.
