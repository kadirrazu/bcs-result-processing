# Preliminary P4: Distribution and Cut-off

The distribution snapshot contains only active registrations with an approved preliminary result row where `candidate_status = active` and `mark IS NOT NULL`.

Rows are grouped by exact mark descending. Each row stores at-mark count and cumulative counts for Total, GG, TT and GT.

Cut-off is governed by a two-step decision record:
- `proposed`: mark + proposal reason + projected pass counts.
- `approved`: approval reason + approving actor + timestamp.
- previous proposals/approved decisions are preserved as `superseded`.

The processing state stores only the current approved cut-off while full decision history remains in `preliminary_cutoff_decisions` and `preliminary_processing_audits`.

Any manual result correction invalidates reconciliation/distribution/finalization snapshots. An already approved cut-off value is preserved but flagged `cutoff_requires_review = true`; it must be reviewed against a regenerated distribution before final result processing.
