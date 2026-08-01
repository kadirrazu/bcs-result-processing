# Written Module — W1 Foundation

## Locked source contract

`user, reg, s001_mark, s002_mark, s003_mark, s005_mark, s007_mark, s008_mark, s009_mark, s010_mark, prs_code, prs_mark, data_source_note`

`data_source_note` is raw source context only. It never changes the Written processing `status` automatically.

## Subject composition

- General: 002, 003, 005, 007, 008, 009, 010 = 900
- Technical: 001, 003, 005, 007, 008, 009, PRS = 900
- PRS is generic processing metadata with full mark 200; the candidate-specific post-related code lives in `prs_code`.

## Rules

- Pass threshold: configurable percentage, default 50%.
- Paper crash: configurable percentage, default 30%.
- 008 + 009 are evaluated together for paper crash.
- High-mark review: configurable percentage, default 75%; 008 + 009 are reviewed as a combined 100-mark subject.
- `AAA` will normalize to `ABS` in the import/validation phase.
- Blank applicable mandatory mark is validation-invalid; ABS/AAA is a legitimate exam status and causes the applicable track to fail.
- Actual marks are never overwritten. Processing writes counted marks separately.
- Written status is ACTIVE / CANCELLED / WITHHELD and is independent from `data_source_note`.
- `written_qualified_track`: GG, TT, GT, GN, T.
- Downstream effective grouping: GG+GN => GG, TT+T => TT, GT => GT.

## W1 database foundation

- written_import_batches
- written_import_staging
- written_results
- written_candidate_marks
- written_processing_states
- written_processing_audits

## Next phase

W2 implements OpenSpout fast staging, identity validation, warning-first review, PRS mismatch warning, high-mark review, issue CSV, and approve/merge.
