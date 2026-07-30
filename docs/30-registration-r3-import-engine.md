# Registration R3 — Auditable Import Engine

## Purpose

R3 turns spreadsheet ingestion into a registration processing stage rather than a simple upsert. It supports large examination datasets, deterministic validation, insert/update reporting, identity-conflict rejection and reversible batch processing.

## Locked identity rules

- `reg` and `user_id` are independently unique.
- Same `reg` + same `user_id`: update the existing candidate.
- Same `reg` + different `user_id`: reject as an identity conflict.
- Different `reg` + same `user_id`: reject as an identity conflict.
- New `reg` + new `user_id`: insert a new candidate.

## Subject rules

- `cadre_category = 1` means GG.
- GG candidates must always have `post_related_subject_code = NULL`.
- A supplied GG post-related subject is normalized to NULL and logged as a warning.
- TT (`2`) and GT (`3`) candidates require a valid `post_related_subject_code`.
- `related_subject_code` is not part of the domain model.

## Date contract

The importer accepts native Excel dates, `YYYY-MM-DD`, `DD/MM/YYYY`, `DD-MM-YYYY` and compact `DDMMYYYY`. MySQL stores native dates as `YYYY-MM-DD`; UI, exports and reports display `DD-MM-YYYY`.

## Transaction and performance model

- The spreadsheet is read in configurable bounded chunks.
- Central master codes are loaded once per import.
- Every chunk uses an examination-database transaction.
- Invalid rows do not prevent valid rows from being persisted.
- Row outcomes are stored in `registration_import_rows`.

## Audit and rollback

Each inserted or updated row stores an after snapshot. Updated rows additionally store a before snapshot. Rollback deletes rows inserted by that batch and restores the before snapshot of updated rows. A batch can be rolled back once.

## Candidate statuses

- `active`
- `cancelled`
- `withheld`

Manual Add/Edit and spreadsheet imports use the same status enum and shared registration business-rule normalizer.
