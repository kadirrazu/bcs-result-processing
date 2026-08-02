# Written W4 — Audited Manual Correction & Stale Protection

## Purpose

W4 adds controlled candidate-level correction to approved Written facts without silently mutating derived result output.

## Editable fields

- Subject marks (`001`, `002`, `003`, `005`, `007`, `008`, `009`, `010`, `PRS`)
- `prs_code`
- Written-only `status`: `active`, `cancelled`, `withheld`
- Operator `comment`

`data_source_note` is read-only. It is imported source context and never drives Written status.

## Mark contract

Manual mark input follows the same source contract as import validation:

- numeric within `0..full_mark`
- `ABS`
- `AAA` (normalized operationally as absence)
- blank is rejected for an applicable mandatory subject

PRS code is required for Technical/GT candidates and must exist in active Post Related Subject master data. A mismatch with the registration PRS remains a warning, not a blocking error.

## Audit contract

Every actual edit requires a reason and writes both:

1. `written_processing_audits` in the examination database
2. the dedicated `written` daily file log

The audit contains actor, IP, user agent, reason, changed fields, before snapshot and after snapshot. Submitting unchanged values creates no audit event.

## Stale contract

Result-affecting changes are:

- any subject mark/raw attendance change
- `prs_code`
- Written `status`

These changes set the Written processing state to `reopened`, mark it stale, unlink the current reconciliation/rule-processing pointers, clear derived PASS/FAIL/totals/qualified-track facts, and require:

1. Generate reconciliation again
2. Process Written rules again
3. Review before finalization

A comment-only edit is audited but does **not** make processing stale.

## Candidate view

The Written candidate view displays:

- original category
- Written qualified track
- effective downstream category (`GN -> GG`, `T -> TT`)
- Written status and validation status
- source note and operator comment
- subject actual/counted marks and paper-crash state
- General/Technical result and fail reasons
- candidate-specific immutable audit history
