# Registration Module

## R1 foundation

R1 establishes the data contract and database foundation only. Registration CRUD,
large Excel import, operational reports and stage approval remain separate milestones.

## Database boundary

`registrations` and `registration_import_batches` are stored in the selected examination
database. Gender, division, district, university, bachelor-subject and related-subject
masters remain in the central database.

Cross-database foreign keys are intentionally avoided. Examination records retain stable
numeric master codes and application services validate those codes against cached central
master maps.

## Registration source contract

- `cadre_category`: `1 = GG`, `2 = TT`, `3 = GT`.
- Bengali name fields are optional and nullable.
- `national_id` is nullable text so leading zeroes are preserved.
- Raw FF, EM and PHC quota fields remain nullable integers.
- `has_quota` is true when FF equals `2`, or EM/PHC contains any non-null value.

## CLI examination migration

Artisan cannot read the browser session used by the examination selector. Use one of these:

```bash
php artisan examination:migrate
```

The command will show an interactive examination list.

You may also pass an ID, slug, BCS number or database name directly:

```bash
php artisan examination:migrate 47
php artisan examination:migrate 47th-bcs
php artisan examination:migrate bcs47
```

Use `--force` in production when required:

```bash
php artisan examination:migrate 47 --force
```

## Performance baseline

The schema includes indexes for identity lookup, category, quota, status and common
reporting combinations. Later imports must use chunked reads and bulk writes and must not
perform central-master queries for each row.
