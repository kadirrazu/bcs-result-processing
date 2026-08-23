# Choice Optimization CO3.14 — OMR Auto-Sanitize, Re-assemble & Approval

Repairable OMR choice defects are warning-level transformations when a clean choice list can be produced.

Covered cases:
- sequence gaps;
- duplicate choices (earliest occurrence retained);
- unknown/inactive/not-in-finalized-Circular choices;
- Written-track-incompatible choices;
- bachelor-subject / PRS incompatible choices;
- parent choices with no eligible output.

Rules:
- Raw OMR source positions/values remain immutable.
- Sequence gaps are compacted before substantive Choice Validation.
- The shared Choice Validation engine removes duplicate/invalid/ineligible choices and performs parent/sub-cadre expansion.
- Remaining output is contiguous #01..#N.
- Source position, action/reason, output position and eligibility detail remain traceable.
- Every automatic removal or positional re-assembly produces a visible warning.
- Warning-only repaired rows remain `valid` and may be Approved/Merged.
- Approval remains blocked for unresolved `invalid`, `conflict`, `decision_review`, or `pending` rows.
- An effective OMR YES still requires `choice_validation_status=valid` and a non-empty clean validated sequence.
