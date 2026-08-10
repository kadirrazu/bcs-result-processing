# Circular Module — Final Implementation Lock

**Module status:** COMPLETED / LOCKED  
**Implementation sequence:** C1 → C2 → C3 → C4 → C5 → C6  
**Purpose:** Final effective and auditable Circular dataset for downstream Choice Validation, Tabulation, Merit, Choice Optimization and Allocation preparation.

## 1. Completed Capability

The Circular Module now provides:

- Cadre Master and Cadre Sub Master based identity resolution.
- Excel import with staging, validation, review and effective dataset approval.
- UI create/edit/delete with mandatory correction reason and audit history.
- Versioned Circular datasets; historical versions remain read-only.
- Real-circular style operational view.
- Searchable Circular Entry Listing, Detail and Eligibility Viewer.
- Bachelor Subject and Registration PRS eligibility mappings for TT entries.
- Downstream stale/outdated propagation after Circular correction.
- Authority Preview PDF, version/hash bound confirmation and finalization.
- Version History, Processing/Edit Audit History and Authority workflow history.
- Final Circular Summary UI + PDF + Excel export.
- A strict finalized-dataset service for downstream modules.

## 2. Authoritative Finalized Dataset Contract

Downstream modules must consume Circular data through `CircularFinalizedDatasetService` (or an equivalent contract preserving the same gate).

A Circular is downstream-ready only when all of the following reference the same version:

`current_version = approved_version = confirmed_version = finalized_version`

and the processing state is `FINALIZED`.

A Draft, merely Approved, or merely Confirmed version must never be consumed as the authoritative eligibility dataset.

## 3. Identity and Eligibility Authority

- Cadre/Sub-Cadre identity is resolved from the Master tables by code.
- Circular stores examination-specific post counts and eligibility snapshots.
- General (`GG`) entries have no Bachelor Subject / PRS restriction.
- Technical (`TT`) entries require both allowed Bachelor Subject and Registration PRS match.
- Registration PRS remains authoritative downstream; Written-stage PRS is not the eligibility authority.
- Multiple Bachelor/PRS values may be supplied in Excel using pipe (`|`) separation and are normalized into child tables.
- Circular entry code is not assumed unique across the entire Circular; repeated effective codes are supported where the real Circular requires subject-wise rows.

## 4. Version and Correction Lifecycle

Approved/Confirmed/Finalized data is never silently overwritten.

A material correction creates/reuses a Draft working version. A no-op edit creates neither a new version nor a false audit event.

After correction the workflow is:

`Draft → Effective Approval → Authority Preview → Confirmation → Finalization`

Historical versions, previews, confirmations and audits are retained.

## 5. Non-Regression Boundary

Circular development must not invalidate or rewrite completed upstream modules:

- Registration
- Preliminary
- Written
- Viva

A Circular change makes only Circular-dependent downstream stages stale where applicable.

## 6. Development Reset Coverage

Circular-owned tables in the development reset registry include:

- `circular_confirmations`
- `circular_authority_previews`
- `circular_processing_audits`
- `circular_import_staging`
- `circular_import_batches`
- `circular_entry_prs`
- `circular_entry_bachelor_subjects`
- `circular_entries`
- `circular_processing_states`

## 7. Next Module Boundary

The next module is **Choice Validation**.

Choice Validation must start from the finalized Circular dataset and candidate original choices. It will resolve Cadre/Sub-Cadre codes, enforce category and finalized-Circular eligibility, remove/report invalid/duplicate/ineligible choices, preserve original and validated forms, and produce auditable validated choice data for later processing.

No Circular business rule should be silently changed after this lock. Any later change is a versioned change request with synchronized documentation, implementation and tests.
