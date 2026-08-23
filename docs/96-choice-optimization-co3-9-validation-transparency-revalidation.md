# Choice Optimization CO3.9 — OMR Validation Transparency & Re-validation

Status: implemented.

- OMR review exposes raw OMR choice, clean expanded/validated OMR choice, errors, warnings and expansion/removal details.
- Only a successful non-empty validated OMR override is labelled downstream safe.
- Operators can explicitly re-validate after an earlier validation run.
- Re-validation uses the same queued validation pipeline and current authoritative source data.
- Raw OMR source is never overwritten by re-validation.
- Previous derived validation output is not authoritative while a new validation run is pending/running.
