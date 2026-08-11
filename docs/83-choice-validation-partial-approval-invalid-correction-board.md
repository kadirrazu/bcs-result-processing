# Choice Validation — Partial Approval, Invalid Correction & Processing Board

## Locked workflow

Row-level invalid Choice source data does not block approval/merge of rows that are already valid.

```
Upload -> Stage -> Validate
                 |-> Valid rows -> Approve/Merge
                 `-> Invalid rows -> Download -> Correct -> Re-upload -> Revalidate -> Approve/Merge
```

File-level template/header errors remain blocking for the whole upload. In particular, an `opt_*` header beyond the configured maximum remains `CHOICE_EXCEEDS_MAXIMUM_ALLOWED_LIMIT`.

## Partial source version

The first valid-row approval assigns the batch a stable `source_version`. Corrected rows from the same batch are subsequently merged into that same source version. Historical source versions remain preserved.

Batch statuses:

- `validated` — validation complete, no approval action yet for the current valid rows.
- `partially_approved` — at least one valid row is approved while one or more invalid rows remain.
- `approved` — every row in the batch has been resolved and approved.

The batch keeps cumulative `approved_rows`; `invalid_rows` is the unresolved correction count after the latest validation.

## Correction workbook

Invalid-row download headers:

- `source_batch_id`
- `source_row`
- `user`
- `reg`
- dynamically generated `opt_01 ... opt_N`
- `validation_error` (informational only)

Only rows that are currently invalid may be replaced through this correction workflow. Valid/approved source rows are protected. The correction upload must retain the exact batch ID, source row IDs and headers.

Every correction writes an immutable `import_correction_entries` record plus Choice processing audit/file log context. After correction, source validation is queued again.

## Downstream stale rule

If corrected rows are merged after a Choice Validation run already exists, the Choice Validation state becomes stale/outdated and must be regenerated. A partial source may be used for operational preview/review, but final Choice Validation cannot be finalized while unresolved invalid source rows remain.

## Processing Status Board

The Choice landing page follows the established Preliminary/Written/Viva pattern with explicit stages:

1. Choice Source Upload & Staging
2. Source Validation
3. Valid Source Approval / Merge
4. Invalid Row Correction
5. Choice Validation Engine
6. Validation Review
7. Choice Validation Finalization

Chunk sizes remain environment-configurable for staging, source validation, source approval and Choice processing.
