# Non-Cadre Processing — Locked Requirements v1.0

**Status:** LOCKED for implementation  
**Date:** 2026-09-18  
**Scope:** BCS Result Processing — Non-Cadre Processing  
**Baseline:** Cadre Allocation Processing is FINAL/COMPLETED. Future Cadre-side changes are outside this module unless separately approved.

## 1. Architectural boundary

1. `Non Cadre Processing` is one top-level examination processing module, placed after `Reporting` in the processing menu.
2. Cadre Processing and Non-Cadre Processing are isolated implementations. Non-Cadre implementation must not modify/refactor finalized Cadre processing business code to add Non-Cadre behavior.
3. Cadre-side data is consumed read-only where required. Non-Cadre processing must never mutate Cadre Allocation, Cadre Circular, Cadre Choice, Merit, or related Cadre authorities.
4. Dependency direction is one-way: **Cadre authoritative result → read-only feed → Non-Cadre**. A Non-Cadre change never stales or changes Cadre processing.
5. All Non-Cadre database tables are stored in the selected examination database through the runtime `exam` connection. Example: 47th BCS Non-Cadre tables live in the 47th BCS examination database.
6. Non-Cadre PHP/classes/views/tests should be grouped under dedicated `NonCadre` / `non-cadre` folders or namespaces wherever practical.

## 2. Module availability and upstream gate

1. Non-Cadre Processing is inactive/non-usable until the authoritative Cadre Allocation result is finalized and current.
2. If Cadre Allocation becomes stale/outdated, Non-Cadre Processing must fail closed: UI actions disabled and backend processing/finalization unavailable.
3. A previously produced Non-Cadre result does not silently become current when Cadre Allocation is rebuilt. Its affected lineage must be revalidated/reprocessed.
4. The Non-Cadre landing page is a hub. Each processing section opens a dedicated subpage for detailed work/status.

## 3. Non-Cadre candidate population

1. Population is sourced from the current finalized Cadre Allocation authority plus current candidate/Merit authorities, read-only.
2. A candidate who received a final Cadre allocation is excluded from Non-Cadre processing regardless of Cadre publication disposition:
   - ACTIVE — excluded
   - WITHHELD — excluded (Cadre seat is retained and candidate may later return ACTIVE)
   - CANCELLED — excluded
3. Only candidates with no Cadre allocation are eligible to feed Non-Cadre processing.
4. Final Non-Cadre allocation validation must independently re-check that no proposed Non-Cadre allocatee has a Cadre allocation. Initial population filtering is not sufficient by itself.

## 4. Internal processing stages

The single module contains these ordered stages:

- **NC1 — Non-Cadre Circular**
- **NC2 — Seat Breakup**
- **NC3 — Non-Cadre Choice Validation & Adjustment**
- **NC4 — Non-Cadre Allocation**
- **NC5 — Non-Cadre Reporting**

Strict dependency/currentness chain:

`Cadre Allocation → NC1 Circular → NC2 Seat Breakup → NC3 Choice → NC4 Allocation → NC5 Reporting`

A result-affecting change to a stage marks every affected downstream authority stale/outdated and non-usable. No old/invalid authority may remain silently usable.

## 5. NC1 — Non-Cadre Circular

### 5.1 Identity and master-data rule

1. There is no Cadre Master or Sub-Cadre Master for Non-Cadre.
2. Canonical post identity is `post_code`.
3. Post title and all post processing/allocation are resolved from the effective Non-Cadre Circular by `post_code`.

### 5.2 Circular Excel columns

`post_grade, post_serial, post_sub_serial, ministry, ministry_bn, entity, entity_bn, post_title, post_title_bn, post_code, post_count, bachelor_subject_codes, status, special_requirement, special_requirement_note`

### 5.3 Validation

- `post_grade`: optional; blank or numeric.
- `post_serial`: required numeric ordering value.
- `post_sub_serial`: optional; blank or numeric.
- `post_code`: required and unique within an effective circular version.
- `post_count`: required positive numeric/integer seat count.
- `bachelor_subject_codes`: optional. Blank means all bachelor subjects are eligible. Multiple allowed codes use pipe syntax, e.g. `121|124|156`.
- `status`: only `ACTIVE` posts are effective/allocatable.
- `special_requirement`: `0` or `1`.
- `special_requirement_note`: mandatory non-empty text when `special_requirement = 1`.

### 5.4 Versioning

Circular is versioned. Only the latest finalized/effective version is authoritative. A result-affecting new/corrected effective version stales NC2, NC3, NC4 and NC5.

## 6. NC2 — Seat Breakup

1. Generated from the current effective NC1 Circular.
2. Workflow: generate breakup template → operator edits → upload → validate → finalize.
3. Quota terminology/philosophy is the same as Cadre Seat Breakup: **MQ / CFF / EM / PHC**.
4. MQ means Merit seat.
5. Seat conservation is mandatory: each post's MQ+CFF+EM+PHC must reconcile to `post_count`.
6. Applicable proportional apportionment uses the established seat-conservation philosophy rather than independent rounding.
7. Finalized Seat Breakup is immutable authority for an allocation run. A new finalized breakup stales NC4 and NC5.

## 7. NC3 — Non-Cadre Choice Validation & Adjustment

### 7.1 Import identity and size

1. Excel identity follows Cadre Choice structure: `user, reg, opt_01 ... opt_N`.
2. Choice values are Non-Cadre `post_code` values, not Cadre codes.
3. Default maximum choices = 20.
4. Maximum is configuration-driven. If configured above 20, import/validation/UI accept the configured number of option columns; processing logic must not hard-code 20.

### 7.2 Original choice preservation

1. Original/raw imported choices are immutable and must always be preserved.
2. Validation never overwrites the original source.
3. Every removed/invalid choice records at minimum its original position, `post_code`, reason code and human-readable reason.
4. Candidate view must allow operators to compare **Original Choice** and **Validated Choice**, following the established Cadre-side usability philosophy without coupling implementations.
5. Manual adjustment produces an audited effective/final choice authority while preserving original and validated histories.

### 7.3 Choice validation

At minimum validate:
- candidate belongs to the current Non-Cadre candidate population;
- `post_code` exists in the latest effective Non-Cadre Circular;
- post is `ACTIVE`;
- no duplicate effective choice;
- candidate bachelor subject satisfies `bachelor_subject_codes`; blank post requirement allows all subjects;
- configured minimum/maximum choice rules;
- source candidate identity (`user`, `reg`) is valid.

Manual changes must record before/after, mandatory reason, operator, time and processing context. Any effective choice change stales NC4/NC5.

## 8. NC4 — Non-Cadre Allocation

### 8.1 Merit authority

1. **Only Common Merit Position** determines candidate ranking.
2. Lower numeric Common Merit Position has higher priority.
3. General Merit Position, Technical Merit Position, cadre-specific merit, Cadre Category and Written Track are not allocation ranking inputs.
4. Choice preference order is the second allocation authority after Common Merit priority.

### 8.2 Eligibility during allocation

Allocation must defensively re-check bachelor-subject eligibility against the current frozen/effective circular authority. A stale or previously validated choice cannot bypass this check.

### 8.3 Quota

1. Candidate CFF/EM/PHC entitlement comes from Registration-authoritative quota data.
2. MQ/CFF/EM/PHC philosophy is the same as finalized Cadre Allocation:
   - do not consume quota if a higher choice is achievable by MQ/general merit;
   - quota is used only when the entitled quota enables a higher-preference post that MQ cannot achieve.
3. One candidate receives at most one effective Non-Cadre allocation.

### 8.4 Re-processing / re-allocation

Allocation is versioned/run-based. Re-processing and re-allocation are supported with historical runs retained and current authority explicit.

## 9. Special Requirement review

1. `special_requirement = 1` does not exclude a candidate from initial allocation solely by itself.
2. Allocation to such a post is provisional until manual review.
3. Operator decision is **APPROVED** or **REJECTED**, with mandatory reason/audit.
4. REJECTED applies to that candidate/post choice, not to the candidate's entire Non-Cadre eligibility.
5. After rejection, the candidate must continue to be considered for later choices according to Common Merit, effective choice, subject eligibility and quota rules.
6. The released post seat must automatically be considered for the next eligible candidate. Cascading re-allocation continues until stable.
7. NC4 cannot be finalized while required special-requirement reviews remain unresolved.

## 10. Final validation and finalization gates

Before NC4 finalization, verify at minimum:
- Cadre Allocation upstream authority is still current;
- no proposed allocatee has any Cadre allocation (ACTIVE/WITHHELD/CANCELLED all prohibited);
- current NC1/NC2/NC3 authorities exactly match the allocation run's frozen inputs;
- Common Merit authority is valid;
- candidate/post bachelor subject eligibility passes;
- choice position is valid in effective final choice;
- seat capacity and MQ/CFF/EM/PHC conservation pass;
- no candidate has duplicate effective allocation;
- all required Special Requirement reviews are resolved;
- result hashes/snapshots required for reproducibility are present.

## 11. Final Non-Cadre result disposition

1. Final statuses: **ACTIVE / WITHHELD / CANCELLED**.
2. WITHHELD retains the Non-Cadre seat and does not trigger re-allocation.
3. CANCELLED excludes the candidate from effective/reportable result.
4. On CANCELLED, seat re-allocation is **operator-selectable**:
   - **Cancel without Re-allocation:** seat remains vacant; other allocations remain effective.
   - **Cancel and Re-allocate:** released seat returns to the allocation process and the required Common-Merit/choice/quota/eligibility cascade is executed.
5. The cancellation action, reason, operator, timestamp and re-allocation decision are audited.

## 12. NC5 — Non-Cadre Reporting

1. Reporting is the final section of the module.
2. Reporting is usable only from a finalized/current NC4 authority.
3. Effective public/result reports exclude WITHHELD and CANCELLED unless a report is explicitly an administrative disposition report.
4. Reporting must bind to the exact finalized Non-Cadre allocation authority and must fail closed if upstream Non-Cadre or Cadre authority is stale.
5. Detailed report families/export formats will be added as explicit requirements without changing the locked processing rules above.

## 13. Audit, provenance and performance

1. Import, validation, approval/finalization, manual adjustment, special review, re-run, stale propagation and disposition changes are auditable.
2. Large imports/processing use chunk/batch/queue patterns where beneficial; avoid row-by-row N+1 database I/O.
3. Each finalized authority should retain enough version/snapshot/hash provenance to detect stale inputs and reproduce the source lineage.
4. Backend gates independently enforce currentness; UI disabled state alone is never treated as a security/integrity control.

## 14. Implementation boundary

The initial implementation must create a dedicated Non-Cadre foundation without altering finalized Cadre business modules. Shared application integration points (navigation, route registration, examination migration registration) may be extended only as needed to expose the isolated Non-Cadre module.
