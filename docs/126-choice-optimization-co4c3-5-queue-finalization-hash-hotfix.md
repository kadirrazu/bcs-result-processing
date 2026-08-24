# Choice Optimization CO4C3.5 — Queue Finalization + Output Hash Hotfix

## Hash fix

The processing-time output hash and finalization-time database hash now use the same canonical row serializer.

This removes differences caused by JSON/null/empty-array representation when rows are hashed before insert versus after Eloquent JSON casting.

## Queue finalization

`Finalize Allocation-ready Choice` now:
- marks state `finalization_queued`;
- dispatches one examination-scoped queue job;
- performs source/output hash verification in the worker;
- updates to `finalized` only after successful verification;
- exposes `finalization_queued` / `finalizing` through the existing JSON polling endpoint;
- reloads the results page once after finalization completes.

A failed finalization records `finalization_failed`, stale reason, and audit information.

No Allocation-ready Choice is made authoritative unless all finalization checks pass.
