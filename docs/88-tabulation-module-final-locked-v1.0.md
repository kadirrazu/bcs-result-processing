# Tabulation Module — Final Locked As-Built Specification v1.0

**Status:** COMPLETE / LOCKED  
**Checkpoint:** 20 August 2026  
**Scope:** Registration → Preliminary → Written → Viva finalized outputs consolidated into the authoritative Tabulation layer.

## 1. Purpose

Tabulation is the versioned processed-result layer immediately before Merit Generation. It does not re-run or reinterpret Written/Viva business rules. It carries forward finalized authoritative upstream outputs and derives the composite values required by Merit.

## 2. Locked Dependency Contract

Tabulation may start only when all of the following are ready/current:

- Registration: approved authoritative dataset exists and no import/approval operation is active.
- Preliminary: result is finalized.
- Written: result is finalized and not stale.
- Viva: result is finalized, not stale, and has a current finalization run.

Circular and Choice Validation have **no dependency relationship with Tabulation**. They must neither gate Tabulation nor mark Tabulation stale.

Result-affecting changes in Registration, Preliminary, Written, or Viva invalidate existing Tabulation. Existing finalized output must never silently remain usable after such an upstream change.

## 3. Population Contract

Only candidates with active Viva attendance status **APPEARED** are included.

- One Tabulation row per candidate.
- Non-appeared candidates are excluded because they have already been filtered by the upstream Viva workflow.
- GT candidates remain one candidate/one row and may carry both General and Technical values.

## 4. Calculation Boundary

Tabulation does **not** recalculate Written/Viva rules.

It carries forward:
- Preliminary mark.
- Finalized Written General/Technical counted totals and P/F status.
- Finalized Viva mark and Viva P/F.
- Candidate category, DOB, graduation year and qualified track from authoritative sources.

It derives:
- General Grand Total = applicable finalized General Written Total + Viva mark.
- Technical Grand Total = applicable finalized Technical Written Total + Viva mark.
- General Merit Eligibility = General Written PASS + Viva PASS.
- Technical Merit Eligibility = Technical Written PASS + Viva PASS.

Non-applicable/failed track grand totals remain null rather than being fabricated.

## 5. Validation and Review

Blocking/data-integrity checks include missing finalized source rows, missing tie-break DOB/category, inconsistent qualified-track P/F, and marks/totals outside configured ranges.

A configurable high-grand-total review warning is supported:
- Default: **75%** of the applicable grand full mark.
- Warning only.
- It does not by itself block processing/finalization.

Rows are classified as `valid`, `warning`, or `error`.

## 6. Processing, Versioning and Integrity

Each generation is a tracked processing run with:
- processing version/run identity;
- progress and counts;
- source snapshot;
- dataset hash;
- summary and failure information.

The source snapshot is compared before processing. If upstream versions/data changed after queuing, the run must not silently continue.

The generated dataset is hashed. Finalization verifies:
- current upstream readiness;
- source snapshot consistency;
- generated row integrity/count;
- dataset hash consistency.

Finalization requires explicit `FINALIZE` confirmation.

## 7. Stale and Rollback Contract

Any result-affecting change from Registration, Preliminary, Written or Viva marks Tabulation stale and also propagates staleness downstream to Merit as applicable.

A stale Tabulation:
- cannot be treated as finalized/current;
- must be regenerated and re-finalized before downstream use.

Authorized rollback to a prior finalization is supported with audit trail and controlled restoration.

## 8. UI / Reporting Contract

Implemented operator surfaces include:
- Landing/readiness and processing board.
- Generation progress/run view.
- Results listing.
- Individual finalized Tabulation view.
- XLSX export.
- Individual PDF report.
- Review summary and dataset-hash visibility.
- Recent audit with operator/user identity, action, time and context.

Candidate/context information that belongs to authoritative earlier modules is fetched at runtime where appropriate instead of being unnecessarily duplicated in Tabulation.

## 9. Downstream Contract

Merit must consume only a current, non-stale, hash-verified finalized Tabulation dataset. Tabulation itself is independent of Allocation.

## 10. Implementation References

Primary implementation:
- `app/Http/Controllers/TabulationController.php`
- `app/Jobs/ProcessTabulation.php`
- `app/Services/Tabulation/*`
- `app/Models/Tabulation*`
- `routes/tabulation.php`
- `database/examination-migrations/2026_08_12_210000_create_tabulation_module_foundation.php`
- `database/examination-migrations/2026_08_13_220000_add_merit_readiness_contract_to_tabulation.php`
- `database/examination-migrations/2026_08_14_004500_add_graduation_year_to_tabulation_results.php`
- `tests/Feature/Tabulation/*`

## 11. Final Lock

No known functional/business-rule work remains in Tabulation at this checkpoint.

**TABULATION MODULE = COMPLETE / LOCKED.**

Future changes require an explicit versioned requirement change and synchronized update of documentation, automated tests and implementation.
