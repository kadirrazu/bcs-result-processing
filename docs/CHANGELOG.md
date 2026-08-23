## 2026-08-24 — Choice Optimization CO4C1.2 Historical Landing Polling
- Added live per-BCS Historical Pull/Re-pull progress indicators and JSON polling on the Choice Optimization landing page.
- Landing reloads once automatically after all observed running historical sources finish.

## 2026-08-24 — Choice Optimization CO4C1.1 Pull Hotfix + Multi-select
- Fixed grouped Eloquent Collection getKey failure in Historical Pull matching.
- Added one-or-many BCS selection with Pull / Re-pull Selected; each selected BCS queues independently.

## 2026-08-24 — Choice Optimization CO4C1 Historical Pull / Re-pull
- Added examination-scoped Historical Previous BCS pull source and match snapshot tables.
- Added queue-based Pull/Re-pull from central EFFECTIVE repository datasets.
- Re-pull replaces the prior workspace match snapshot for that BCS instead of accumulating pull versions.
- Matching uses Written-qualified candidates and exact SSC roll + SSC year + primary birth date, with supporting name/NID/HSC/secondary-DOB review signals.
- Added update-available detection when the central effective repository version changes.

## 2026-08-23 — Choice Optimization CO4B.3 Row Detail + Master Menu Group
- Added per-row View action and read-only Previous BCS historical row detail page.
- Kept the existing full dataset tabular view.
- Historical Data is now a section inside Master Data below Registration Masters, not a top-level menu.

## 2026-08-23 — Choice Optimization CO4B.2 Repository Polish
- Added full read-only Previous BCS dataset detail view with warnings/errors/system status.
- Corrected repository status badge colors, including green EFFECTIVE.
- Moved Previous BCS Repository into a new Historical Data navigation group after Registration Masters.

## 2026-08-23 — Choice Optimization CO4B.1 Cadre Warning + Repository Search
- Changed unmatched historical cadre abbreviation from blocking error to warning-only while preserving the source abbreviation.
- Added multi-field dataset search and filters for status/warning, cadre, SSC year and HSC year.

## 2026-08-23 — Choice Optimization CO4B Previous BCS Repository Validation & Authority
- Added queued global repository validation with duplicate identity checks, secondary-DOB warning and central cadre/sub-cadre abbreviation validation.
- Added deterministic dataset hash and explicit EFFECTIVE approval gate.
- Added one-current-effective-version authority per BCS; prior effective versions are preserved as superseded.
- Added strict effective-dataset reader service for the upcoming current-workspace matching phase.

## 2026-08-23 — Choice Optimization CO4A Global Previous BCS Repository
- Added central BCS-wise Previous BCS Recommendation Repository with versioned dataset uploads.
- Added queue-based XLSX/CSV staging, JSON polling, raw source preservation, strict header contract and DOB normalization.
- `b_date` supports DDMMYY/DDMMYYYY; optional `dob` is stored as secondary DOB evidence.
- CO4A does not approve/effect datasets and does not yet search current examination candidates; those remain CO4B/CO4C.

# Documentation Changelog

## Unreleased

### Added
- Project vision and scope foundation.
- Requirements baseline including preliminary cut-off workflow and category-wise written result publication.
- System architecture and examination data isolation strategy.
- ADR-001: shared application code with a separate physical database per BCS examination.
- Folder structure guidance.
- Coding and commenting standards.
- Naming conventions.
- CRUD vs Action classification matrix.
- Action, Service, Domain Rule, Repository, Model, and DTO boundaries.
- Database design principles.
- Standard module development workflow.


## 2026-08-20 — Tabulation Module final lock
- Added final as-built Tabulation specification and locked dependency contract.
- Locked Tabulation readiness to Registration + Preliminary + Written + Viva; Circular/Choice Validation do not gate or stale Tabulation.
- Documented APPEARED-only population, one-row-per-candidate/GT dual-track semantics, derived grand totals, merit eligibility, 75% warning-only high-total review, source snapshots, dataset hash, finalization, rollback, reporting and audit behavior.
- Status: **TABULATION MODULE = COMPLETE / LOCKED**.

## 2026-08-20 — Merit Generation Module final lock
- Added final as-built Merit Generation specification.
- Locked Merit readiness to finalized/hash-verified Circular + Tabulation + Choice Validation with Circular-version parity for Choice Validation.
- Documented deterministic common/general/technical ranking, locked tie-break order, cadre-wise merit, `all_merit_tech`, stale handling, finalization, rollback, review/reporting and export contracts.
- Status: **MERIT GENERATION MODULE = COMPLETE / LOCKED**.

## 2026-08-20 — Completion checkpoint through Merit
- Added formal project completion/lock checkpoint through Merit.
- Confirmed no known functional/business-rule work remains from Registration through Merit at this checkpoint.
- Next development boundary: Choice Optimization, then integration with the existing validated Allocation Engine.

## v1.8 - Registration Module
- Added central gender, division, district and university masters.
- Added examination-specific registration and import-batch schema.
- Added high-volume Excel import, CRUD, filters, reports and tests.
- Corrected processing navigation to Registration → Preliminary → Written → Viva → Choices.

## 2026-08-11 — Choice Validation CV1/CV2 foundation
- Added config-driven Choice source template (`user`, `reg`, `opt_01..opt_N`; default N=20).
- Added strict blocking header guard `CHOICE_EXCEEDS_MAXIMUM_ALLOWED_LIMIT` for option columns beyond configured maximum.
- Added raw source validation for minimum one choice and sequence gaps.
- Added versioned Choice source storage using parent + positional child records, preserving full source snapshot without a fixed opt-column schema ceiling.
- Added Choice source import/review/approval workspace, navigation and reset-registry integration.
