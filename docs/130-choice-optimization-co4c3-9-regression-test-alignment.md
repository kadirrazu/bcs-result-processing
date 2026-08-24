# Choice Optimization CO4C3.9 — Regression Test Alignment

Tests-only maintenance patch.

The older CO4C3.1 contract still asserted the superseded wide-table Choice Lineage header and horizontal non-wrapping lane. CO4C3.2/CO4C3.3 intentionally replaced that UI with:

- full-width stacked candidate records;
- explicit Current Candidate / Previous BCS Match context;
- Input Effective Choice above Allocation-ready Choice;
- wrapped vertical choice chips;
- no forced horizontal overflow.

The test is aligned with the finalized UI contract.

No application code or business logic changed.
