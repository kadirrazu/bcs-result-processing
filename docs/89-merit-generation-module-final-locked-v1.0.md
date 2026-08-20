# Merit Generation Module — Final Locked As-Built Specification v1.0

**Status:** COMPLETE / LOCKED  
**Checkpoint:** 20 August 2026  
**Scope:** Deterministic common/general/technical/cadre-wise merit generation from finalized Circular + Tabulation + Choice Validation datasets.

## 1. Purpose

Merit is a separate, regeneratable, versioned layer after Tabulation. It ranks eligible candidates deterministically and prepares the authoritative merit input required by the later Choice Optimization / Allocation integration.

Merit does not recalculate Tabulation, Written or Viva business rules.

## 2. Locked Dependency Contract

Merit may run only when all three authoritative datasets are current and finalized:

1. Circular
2. Tabulation
3. Choice Validation

Strict processing/finalization gates re-verify finalized dataset hashes.

Choice Validation must also have been produced against the same current Circular version. A Circular-version mismatch blocks Merit.

Result-affecting changes propagate as follows:
- Registration / Preliminary / Written → Tabulation → Merit.
- Viva → Tabulation + Choice Validation → Merit.
- Circular → Choice Validation + Merit.
- Choice Validation → Merit.
- Tabulation → Merit.

Merit must never silently continue on stale upstream data.

## 3. Ranking Population

Merit reads the finalized Tabulation run.

Eligibility:
- General ranking: `general_merit_eligible`.
- Technical ranking: `technical_merit_eligible`.
- Common ranking: candidate is eligible on at least one of General or Technical tracks.

Candidates not eligible for either track remain unranked with the appropriate status reason.

## 4. Locked Tie-Break Order

For an applicable merit scope, ranking is deterministic and sequential:

1. Higher applicable Grand Total.
2. If tied, higher applicable Written Total.
3. If tied, higher Preliminary mark.
4. If tied, older candidate / earlier date of birth.
5. Graduation year and stable identity fields are deterministic technical fallbacks for otherwise exact duplicate data; they do not replace the business tie-break rules.

Final merit positions are unique sequential integers: `1, 2, 3, ...`, never competition ranking such as `1, 1, 3`.

## 5. General, Technical and Common Merit

The module generates:
- Common Merit Position.
- General Merit Position.
- Technical Merit Position.

For common merit, the applicable surviving/eligible track determines the comparable grand/written values. GT candidates may participate in both General and Technical scopes where eligible.

## 6. Cadre-wise Merit

Cadre-wise merit is built only from:
- current finalized Choice Validation;
- current Circular entries;
- candidates having the applicable source merit position.

Each cadre rank row records:
- candidate/registration;
- cadre code and abbreviation;
- cadre type;
- cadre merit position;
- source merit position;
- validated choice position;
- qualification basis.

For Technical cadres, `all_merit_tech` stores the cadre abbreviation → unique cadre merit rank map, e.g. `{"MEDI": 5}`.

Cadre-wise ranking is deterministic and uses the appropriate General or Technical source merit according to the Circular entry type.

## 7. Processing, Versioning and Integrity

Every Merit generation is a tracked processing run with:
- processing version;
- source snapshot;
- progress;
- total/ranked/cadre counts;
- generated dataset hash;
- summary and failure state.

The queued source snapshot is compared again when processing begins. Any change in Circular, Tabulation or Choice Validation aborts the run.

Finalization requires:
- non-stale state;
- completed generation;
- strict upstream readiness/hash verification;
- exact source-snapshot match;
- generated result-count integrity;
- generated dataset-hash integrity;
- explicit `FINALIZE` confirmation.

## 8. Stale and Rollback Contract

If an authoritative upstream finalized dataset changes or becomes unavailable, Merit is marked stale.

A stale Merit dataset:
- is not authoritative;
- cannot be finalized/consumed as current;
- must be regenerated and re-finalized.

Authorized rollback to a prior Merit finalization is supported with integrity checks and audit trail.

## 9. UI / Review / Reporting Contract

Implemented operator surfaces include:
- Landing/readiness board.
- Generate / regenerate workflow.
- Processing progress.
- Review summary.
- Results listing with sorting/filtering.
- General/technical/common merit visibility.
- Cadre-wise merit listing/search.
- Candidate individual view.
- Original vs validated choice visual review.
- Audit information.
- Final XLSX export.
- Cadre-specific XLSX export.
- Individual PDF report.

Candidate/context fields are presented from authoritative source modules where appropriate.

## 10. Allocation Boundary

Merit remains independent of Allocation.

The later Allocation/Optimization layer must consume prepared finalized inputs and must not recalculate Tabulation or Merit.

Relevant output contract includes:
- General merit position.
- Technical merit position.
- `all_merit_tech` cadre-wise rank map.
- Validated choices and other required candidate allocation inputs.

The already validated Allocation Engine is not redesigned by the Merit module.

## 11. Implementation References

Primary implementation:
- `app/Http/Controllers/MeritController.php`
- `app/Jobs/ProcessMeritGeneration.php`
- `app/Services/Merit/*`
- `app/Models/Merit*`
- `app/Policies/MeritResultPolicy.php`
- `app/Reports/Pdf/MeritIndividualPdfReport.php`
- `routes/merit.php`
- `database/examination-migrations/2026_08_14_005000_create_merit_generation_module.php`
- `tests/Feature/Merit/*`

## 12. Final Lock

No known functional/business-rule work remains in Merit at this checkpoint.

**MERIT GENERATION MODULE = COMPLETE / LOCKED.**

Future changes require an explicit versioned requirement change and synchronized update of documentation, automated tests and implementation.
