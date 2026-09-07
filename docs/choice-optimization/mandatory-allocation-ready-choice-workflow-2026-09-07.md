# Choice Optimization — Mandatory Allocation-ready Choice Workflow

Decision date: 2026-09-07

## Locked flow

1. Choice Validation keeps every choice that is valid under the retained Master/Circular/duplicate/sequence/Bachelor/PRS rules. Written surviving-track mismatch alone does not remove a choice.
2. Merit Generation uses Finalized Validated Choice but explicitly creates cadre merit only for a cadre type whose Written track survived.
3. Choice Optimization is mandatory; the legacy examination-wide Optimization YES/NO bypass is retired.
4. At each Optimization run, Previous BCS Repository is selected by default when INCLUDED sources exist. Operator may deselect it. If selected, all INCLUDED source snapshots are used.
5. Google Form remains examination-level optional YES/NO. When YES and the latest dataset is valid/accepted, Google Form appears in run options and is selected by default. Operator may deselect it.
6. All selected Previous BCS + Google Form recommendations are consolidated first into one unique historical recommendation set. Duplicate cadre evidence is de-duplicated while source provenance is retained. Source disagreement is non-blocking.
7. Historical matching runs exactly once against the current finalized validated/effective choice sequence. The earliest/highest-preference exact matched cadre defines the single cutoff. Invalidated current-BCS choices are never restored or used as fallback.
8. The Written-track Filter is mandatory, cannot be disabled, and always runs **after** historical cutoff. This ordering is a hard invariant.
9. Final output is the Allocation-ready Choice. Empty output is valid and means the candidate enters no allocation queue.
10. Allocation A2 performs no choice business filtering. It only verifies and freezes the current finalized Choice Optimization output/hash.

## Audit / tracking

Candidate output preserves:
- input validated/effective choice;
- consolidated historical recommendations with source provenance;
- matched cutoff;
- choices removed by historical cutoff;
- choice sequence after historical cutoff;
- choices removed by Written-track Filter;
- per-choice track removal detail: position, code, cadre type, Written track, reason code and reason message;
- final Allocation-ready Choice.

Run snapshot preserves exact selected component flags, exact Previous BCS source IDs, exact Google Form batch ID, Choice Validation/Circular hashes and final output hash. Historical source changes only stale a finalized run when that source component participated in the run.
