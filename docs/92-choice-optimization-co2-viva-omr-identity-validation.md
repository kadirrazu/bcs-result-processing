# Choice Optimization CO2 — Viva OMR Import & Identity Validation

Status: implemented foundation checkpoint.

CO2 adds raw Viva OMR staging and validates OMR identity against the current finalized, non-stale Written-qualified population before any choice override is considered.

Locked source columns are `reg`, `change_choice`, and configurable `opt_01 ... opt_N` (default 20).

Rules implemented in this checkpoint:

- Raw OMR payload and raw registration are preserved.
- `change_choice` must be YES or NO.
- NO expects all OMR choice fields blank.
- YES requires at least one new choice.
- OMR registration must resolve to a finalized Written-qualified candidate.
- Duplicate OMR registration claims are conflicts and are not silently de-duplicated.
- A registration resolving to multiple Written-qualified rows is also a conflict.
- Conflict/correction uses a separate effective registration; the raw registration remains unchanged.
- Registration correction requires a reason and creates a Choice Optimization audit event.
- After correction, the OMR batch must be revalidated.
- OMR choice-code/business eligibility revalidation and actual override application are intentionally deferred to CO3.
