# Choice Validation Module — CV1/CV2 Foundation

Status: implementation started after Circular Module lock.

## Locked source contract

- Excel columns: `user`, `reg`, then dynamic `opt_01 ... opt_N`.
- `N = config('choice-validation.maximum_allowed_choices')`; default `20`.
- No hard database storage ceiling is tied to N.
- A spreadsheet containing any `opt_*` column above N is a blocking import-level error with reason `CHOICE_EXCEEDS_MAXIMUM_ALLOWED_LIMIT`.
- Each candidate must provide at least one raw choice. Zero choices => INVALID / `NO_CHOICE_PROVIDED`.
- A blank followed by a later populated option => INVALID / `CHOICE_SEQUENCE_GAP`; source positions are never shifted.
- Raw source remains separate from later validation/optimization output.

## Storage

Approved source is stored as a parent `choice_validation_sources` row plus positional `choice_validation_source_items`; the complete source row is also retained as `source_snapshot`. This preserves Excel alignment without fixed physical `opt_XX` database columns and remains adaptable above 30 choices.

## Current phase scope

CV1/CV2 implements configuration, source schema, dynamic template, strict import/header validation, Registration identity validation, source review and versioned source approval. Cadre/Circular eligibility and parent/sub expansion belong to CV3/CV4.
