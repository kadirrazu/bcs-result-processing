# Choice Optimization CO4C3.6 — Finalization Failure State Hotfix

- Moves every finalization prerequisite/source/output hash verification inside the finalization `try/catch`.
- A verification exception can no longer leave processing state stuck at `finalization_queued`.
- Any finalization failure now persists:
  - `status = finalization_failed`
  - `is_stale = true`
  - exact exception message in `stale_reason`
  - `CHOICE_OPTIMIZATION_FINALIZATION_FAILED` audit event.
- Existing JSON polling therefore sees `running=false`, reloads once, and exposes the exact failure reason on the results page.
- No trimming or Allocation-ready Choice business rule changed.
