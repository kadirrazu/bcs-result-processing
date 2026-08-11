# Choice Validation CV5 UI/Audit/Status Refinement v2.2.3

This refinement keeps the CV5 correction overlay model and the v2.1.4/v2.1.5
correctness/performance fixes intact.

## Changes

- Candidate name is shown with reg/user in results and details.
- Manual correction history shows exact old -> new value per opt position,
  correction reason, operator and revalidation time.
- Generic not_applicable is refined into explicit candidate statuses:
  - not_applicable_due_to_fail_in_viva
  - not_applicable_due_to_inactive_viva_result
  - not_applicable_due_to_missing_viva_result
  - not_applicable_due_to_unresolved_written_track
- Choice item reason trail uses explicit machine-readable reasons such as
  CANDIDATE_FAILED_IN_VIVA.
- Results include a Not Applicable breakdown.
- Landing Processing Status Board is simplified from 7 granular rows to 4
  operational stages:
  1. Source Import & Validation
  2. Source Approval & Correction
  3. Choice Validation & Review
  4. Finalization

No schema change is required.
