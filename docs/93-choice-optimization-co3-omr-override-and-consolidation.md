# Choice Optimization — CO3 Viva OMR Override & Consolidation

Status: implementation checkpoint.

## Locked behavior

- OMR staging, validation and approval/consolidation run through queue jobs.
- Operator pages use JSON polling for progress; timed full-page refresh is not used.
- OMR registration is validated against the finalized Written-qualified population.
- `change_choice=NO` with populated OMR options is an operator-decision case, not an automatic discard or automatic override.
- Operator compares the finalized Validated Choice with raw OMR options and explicitly chooses either:
  - consider NO as YES and keep/revalidate the OMR options; or
  - keep NO and discard the OMR options from the effective pipeline.
- Raw OMR source remains unchanged in both cases and the operator decision/reason is audited.
- Effective YES OMR choices are revalidated with the finalized Circular and the reusable Choice Validation engine using the candidate's Written-qualified track.
- Approval is queued and builds one consolidated effective-choice dataset from finalized Choice Validation rows, overlaying only valid audited OMR overrides.
- CO3 does not yet perform previous-BCS recommendation matching or previous-service trimming; those belong to later Choice Optimization stages.
