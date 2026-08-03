# Viva V2 — Candidate Mapping Import

V2 implements the first Viva source import: `user, reg, code`.

Locked checks:

- Written result must be finalized and current.
- `REG + USER` must identify the same registration candidate.
- Candidate must be active and qualified in the finalized Written result.
- `code` is treated as a string and leading zeroes are preserved.
- Duplicate code inside the source batch is invalid.
- A code already assigned to another candidate is invalid.
- Invalid/identity-conflict rows may use the shared invalid-row correction workflow before approval.
- Only valid rows are approved into `viva_candidate_mappings`.

The landing summary cards use equal-height card bodies for consistent visual alignment.
