# Choice Optimization CO3.16 — Contract Test Alignment

This patch changes tests only. Application behavior is unchanged.

- Fixes PHP variable interpolation bugs in source-contract assertions for `$omr`, `$status`, and `$batch`.
- Aligns older CO3.3/CO3.8 UI assertions with the current minimal OMR review design.
- Keeps the authoritative contracts: compact Category/Written Track context, Registration/Validated/OMR lineage, single horizontal #01→#20 choice lane, AJAX operator confirmation, functional re-validation, warning/operator-confirmed filters, and auto-sanitized approval safeguards.
