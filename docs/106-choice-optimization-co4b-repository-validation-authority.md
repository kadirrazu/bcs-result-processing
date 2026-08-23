# Choice Optimization CO4B — Previous BCS Repository Validation & Authority

## Lifecycle

`queued → processing → staged → validation_queued → validating → validated → effective`

If blocking row problems exist:

`validating → validation_failed`

When a newer version becomes effective, the previous effective version becomes `superseded`.

## Validation

Blocking:
- duplicate previous-BCS registration inside the same dataset;
- duplicate primary core identity: SSC roll + SSC year + primary `b_date`;
- unknown cadre abbreviation outside the central Cadre/Sub-Cadre master namespace;
- source/date/required-field errors carried forward from CO4A.

Warning:
- optional secondary `dob` differs from primary `b_date`; `b_date` remains authoritative for later matching.

Cadre abbreviations are normalized to uppercase while the raw source payload remains preserved.

## Authority

- Staged or validated data is not authoritative for matching.
- A zero-blocking-error validated dataset receives a deterministic SHA-256 dataset hash.
- Operator must explicitly type `EFFECTIVE`.
- Approval recomputes the dataset hash before authority changes.
- Only one effective version exists per BCS repository.
- A prior effective version is preserved as `superseded`.
- `PreviousBcsEffectiveDatasetService` is the future CO4C read boundary.
