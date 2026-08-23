# Choice Optimization CO3.8 — OMR Full Validation, Expansion & Review UI Polish

Status: implementation checkpoint

## Effective OMR override validation

Any OMR choice that becomes effective through either an explicit `YES` or an operator decision to treat `NO + options` as `YES` must pass the same substantive Choice Validation layers before approval:

1. Choice source row rules are reused through `ChoiceRowRuleValidator` for sequence/blank-position and applicable choice-count validation.
2. `ChoiceValidationEngine` is reused against the current finalized Circular and authoritative Registration data.
3. Duplicate/repeated choices, unknown/inactive codes, codes not in the finalized Circular, track restrictions, bachelor-subject eligibility and Registration PRS compatibility follow the existing Choice Validation engine contract.
4. Parent cadre choices use the same parent/sub-cadre expansion logic. Eligible sub-cadres are emitted in finalized Circular `sub_serial` order. If a parent has no eligible sub-cadre for the candidate, that parent choice is removed with the same validation reason used by Choice Validation.
5. Only the resulting clean `validated_omr_choice_codes` sequence may become the effective OMR override.

Raw OMR choices remain preserved. Validation/expansion/removal reasons remain in the OMR validation details for audit and administrative review.

## Approval safety

Approval contains an additional hard guard: an effective OMR `YES` row must have `choice_validation_status = valid` and a non-empty validated OMR choice sequence. Raw/unvalidated/empty OMR overrides cannot flow into the effective choice dataset or later Allocation.

## Review page UI

On `/choice-optimization/omr/{batch}`:

- candidate context remains in the first row;
- all context cells use equal-height title/header areas and equal-height value/body areas;
- Original Category displays only the compact category code (`GG`, `TT`, `GT`), without verbose labels;
- Written Qualified Track remains compact;
- choice comparison remains left-to-right in preference order `#01 → #20` (or the configured maximum).

No schema change is required for CO3.8.
