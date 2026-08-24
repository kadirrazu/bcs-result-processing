# Choice Optimization CO4C3.7 — Canonical Output Hash Order Hotfix

Root cause of the remaining finalization hash mismatch:

- processing-time output hash iterated rows in the source/input iteration order;
- finalization-time database hash explicitly read rows ordered by `registration_id`;
- SHA-256 is order-sensitive, so the same candidate rows in a different order produced a different dataset hash.

Fix:
- processing-time hash now sorts the derived rows by `registration_id` before canonical serialization;
- database hash already uses the same `registration_id` ordering;
- both sides continue to share `canonicalOutputRow()`.

UI polish:
- `finalization_failed` now shows one red Finalization failed alert rather than duplicating the same reason as both STALE and Finalization failed.

Also fixes the CO4C3.6 test `$e` interpolation assertion.
