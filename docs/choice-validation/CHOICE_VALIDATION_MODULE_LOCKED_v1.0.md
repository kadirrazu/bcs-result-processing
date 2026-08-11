# Choice Validation Module — Final Lock v1.0

**Status:** COMPLETED / LOCKED  
**Completion phases:** CV1–CV7  
**Downstream canonical service:** `ChoiceValidationFinalizedDatasetService`

## 1. Source Contract

Choice source workbook columns are dynamic:

`user, reg, opt_01 ... opt_N`

where `N = config('choice-validation.maximum_allowed_choices')`, default 20.

Rules:

- At least one raw Choice is required.
- `CHOICE_EXCEEDS_MAXIMUM_ALLOWED_LIMIT` is a file/header-level blocking error.
- `CHOICE_SEQUENCE_GAP` is a row-level invalid error.
- Row-level invalid data does not block approval/merge of already-valid rows.
- Invalid rows are downloaded, corrected and re-uploaded through the targeted correction loop.
- Original imported source remains immutable.

## 2. Source / Processed Separation

- `choice_validation_sources` + source items preserve approved raw/source alignment.
- `choice_validation_results` contains candidate-level processed output.
- `choice_validation_items` stores source-position resolution trail.
- `choice_validation_manual_corrections` stores audited correction overlay.
- Full rerun and single-candidate revalidation use the latest correction overlay while preserving raw source.

## 3. Candidate Eligibility

Choice Validation consumes finalized Viva/Written context:

- GG / GN → General track
- TT / T → Technical track
- GT → General + Technical

Explicit Not-Applicable outcomes include:

- `NOT_APPLICABLE_DUE_TO_FAIL_IN_VIVA`
- `NOT_APPLICABLE_DUE_TO_INACTIVE_VIVA_RESULT`
- `NOT_APPLICABLE_DUE_TO_MISSING_VIVA_RESULT`
- `NOT_APPLICABLE_DUE_TO_UNRESOLVED_WRITTEN_TRACK`

Viva FAIL is a legitimate examination outcome and remains distinguishable from missing/inconsistent data.

## 4. Circular Eligibility

Only the strict finalized Circular dataset is consumed.

General choice:
- no Bachelor Subject / PRS restriction.

Technical choice:
- Registration Bachelor Subject IN finalized Circular allowed set
- AND Registration PRS IN finalized Circular allowed PRS set.

Registration PRS remains authoritative downstream.

## 5. Main / Sub-Cadre Resolution

- Main and Sub codes share the master logical namespace.
- Direct Sub Cadre source choice is supported.
- Parent/Main choice expands to eligible finalized-Circular sub-cadre rows in `sub_serial` order where applicable.
- Removed/expanded/duplicate output remains traceable to the source choice position.

## 6. Finalization Gate

Choice Validation cannot finalize while any of the following is true:

- source is not approved;
- invalid source correction remains unresolved;
- current validation is missing/stale/running/failed;
- current result count does not equal approved source count;
- manual correction awaits revalidation;
- current finalized Circular differs from the Circular version used by the validation run.

Finalization requires a mandatory note.

## 7. Finalization Integrity

`choice_validation_finalization_runs` preserves append-only finalization history.

The exact processed dataset is bound to a SHA-256 dataset hash.

The processing state points to:

- current validation version;
- finalized validation version;
- latest finalization run;
- finalized timestamp.

Material source/manual correction clears the active finalization pointer and requires revalidation/re-finalization. Historical finalization rows are preserved.

## 8. Downstream Contract

Future Tabulation/Merit/Choice Optimization/Allocation preparation MUST use:

`ChoiceValidationFinalizedDatasetService`

Important methods:

- `finalizedVersion()`
- `results()`
- `choiceReadyResults()`
- `validatedChoiceMap()`

The service refuses stale/non-finalized datasets and verifies the stored dataset hash before returning authoritative data.

## 9. Final Reports

- Final read-only UI report
- PDF aggregate/finalization summary
- Excel candidate-level finalized output
- Candidate detail audit/review remains available

## 10. UI Lock

Processing board is intentionally limited to four operational stages:

1. Source Import & Validation
2. Source Approval & Correction
3. Choice Validation & Review
4. Finalization

Candidate details show Registration/User, Candidate Name, Registration Category, Written-derived Category, Current Track, Original/Effective/Validated choices, visual removal/expansion markers and manual correction history.

Original Imported Choices show normal unchanged codes without a redundant `Retained` label.

## 11. Non-Regression Boundary

Registration, Preliminary, Written, Viva and Circular are upstream locked modules and must not be redefined by Choice Validation.

Choice Optimization remains a separate later module. Previous BCS service cutoff/optimization is NOT performed in Choice Validation.

**END OF CHOICE VALIDATION MODULE FINAL LOCK v1.0**
