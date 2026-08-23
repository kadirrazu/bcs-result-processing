# Choice Optimization CO3.9.1 — Functional OMR Re-validation

- Adds an explicit visible batch-level `Re-validate OMR Choices` action.
- Uses a dedicated POST re-validation route and controller action.
- Re-validation invalidates only derived OMR validation fields; raw OMR evidence and operator resolutions remain preserved.
- The batch immediately becomes `validation_queued`.
- The existing queued OMR validation job performs the full validation again.
- The review page shows raw OMR choice, expanded/validated OMR choice, errors, warnings, expansion/removal details, and downstream-safety status.
