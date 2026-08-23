# Choice Optimization CO3.13 — Re-validation Batch Scope Hotfix

- Fixes `Undefined variable $batch` in `ChoiceOptimizationOmrValidationService`.
- The examination transaction closure now captures the current OMR batch.
- This allows the shared Choice Validation row-rule validator to safely read the batch-configured maximum choice count during first validation and re-validation.
- No source data, schema, or business rule changes.
