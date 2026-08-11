# Choice Validation CV5 — Review, Manual Correction & Candidate Revalidation

## Locked implementation

- Result listing remains filterable by status, search and machine-readable reason code.
- Candidate detail exposes the full source resolution trail.
- Manual correction requires a reason.
- Imported raw Choice source is never overwritten.
- A manual correction is stored as an audited overlay in `choice_validation_manual_corrections`.
- The latest correction overlay becomes the effective source for both single-candidate revalidation and future full Choice Validation runs.
- Saving an actual correction immediately revalidates the candidate against the same finalized Circular version and current Validation version.
- No actual source change creates no correction/audit event.
- Candidate revalidation recalculates the current run aggregate counts.
- DB processing audit and a dedicated daily Choice correction file log are written.
- Finalization remains outside CV5 and will be implemented in CV6.
