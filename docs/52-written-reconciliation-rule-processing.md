# Written W3 — Reconciliation and Rule Processing

W3 adds two controlled steps after an approved Written import.

1. **Reconciliation** compares finalized Preliminary PASS candidates with the approved Written snapshot.
   - Eligible = Preliminary PASS.
   - Appeared = approved Written row exists.
   - Completely absent = eligible candidate missing from the Written snapshot OR every applicable Written subject is ABS/AAA.
   - Mandatory-subject absent = appeared candidate with a mixture of numeric marks and one or more applicable ABS/AAA values.

2. **Written rule processing** runs in the queue and keeps source truth separate from counted truth.
   - Individual subjects crash below the configured percentage.
   - 008 + 009 are evaluated only as a combined crash group.
   - Actual marks are never overwritten; crashed counted marks become zero.
   - General and Technical tracks are evaluated independently.
   - GT may derive `GT`, `GN`, `T`, or no qualified track.
   - Effective downstream grouping is GG = GG + GN, TT = TT + T, GT = GT only.

This phase produces a result preview (`processing_ready`) but does not finalize/publish Written results. Final freeze/publication and audited manual correction are intentionally left for the next phase.
