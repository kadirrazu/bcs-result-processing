# Architecture Decisions

## Document Purpose

This document records material architecture decisions. Approved decisions are not silently edited or removed. A later decision may supersede an earlier decision while preserving the historical record.

---

# ADR-001 — Examination Data Isolation Strategy

- **Status:** Accepted
- **Date:** 2026-07-26
- **Scope:** Database topology, module design, migrations, processing, reporting, backup, and archival

## Context

The platform must process multiple BCS examinations over many years. Candidate, marks, tabulation, merit, choice, and allocation datasets can be large and operationally sensitive.

The project owner has explicitly rejected storing operational data from many BCS examinations in the same physical tables.

The architecture must therefore provide strong data isolation without duplicating application code for each BCS.

## Decision

The system will use:

1. One shared Laravel application and codebase.
2. One central application database.
3. One separate physical examination database for each BCS.
4. A common schema contract across examination databases.
5. An explicit examination context to select the active database connection.

Examples:

```text
Central database: bcs_platform
Examination database: bcs_exam_47
Examination database: bcs_exam_48
Examination database: bcs_exam_49
```

Operational data from different BCS examinations must not be stored together in the same candidate, preliminary, written, tabulation, merit, choice, or allocation tables.

## Central Database Responsibilities

The central database owns:

- Users and authorization.
- Designations.
- Examination registry.
- Shared master data where appropriate.
- Global settings.
- Central monitoring summaries.

## Examination Database Responsibilities

Each examination database owns its own:

- Candidates.
- Imports and validation issues.
- Preliminary marks, cut-offs, and results.
- Written and viva marks.
- Tabulation.
- Merit.
- Choices.
- Post snapshots and allocation.
- Processing runs and audit records.

## Explicitly Rejected Alternatives

### Rejected: One Shared Operational Table per Entity

Example:

```text
candidates with examination_id
written_marks with examination_id
merit_results with examination_id
```

Reason for rejection:

- Does not satisfy the approved physical data-isolation requirement.
- Increases the impact of accidental unscoped queries.
- Makes examination-specific backup, restore, and archival less direct.

### Rejected: Dynamic Table Names in One Database

Example:

```text
bcs47_candidates
bcs48_candidates
bcs47_written_marks
bcs48_written_marks
```

Reason for rejection:

- Causes uncontrolled table growth.
- Complicates migrations, foreign keys, models, tests, and maintenance.
- Encourages string-based table selection and examination-specific code.

### Rejected: Separate Laravel Application per BCS

Reason for rejection:

- Duplicates code and security fixes.
- Causes architectural drift.
- Makes improvements and bug fixes difficult to synchronize.

## Consequences

### Positive

- Strong physical isolation between BCS datasets.
- Lower risk of accidental cross-examination modification.
- Easier per-BCS backup, restore, archival, and read-only transition.
- Shared application behavior and consistent business rules.
- A completed BCS can be moved or archived independently.

### Costs and Constraints

- Dynamic connection management must be implemented carefully.
- Examination migrations require orchestration.
- Cross-BCS reporting requires aggregation across databases.
- Cross-database distributed transactions will not be assumed.
- Schema versions must remain controlled and observable.

## Required Implementation Rules

- No examination-specific operation may run without an explicit examination context.
- Models for examination data use the controlled examination connection.
- Table names remain generic inside each examination database.
- Separate controllers or services are not created merely because the BCS number differs.
- Examination database creation and migration use approved commands or services.
- Connection state must be reset safely between HTTP requests, queue jobs, commands, and tests.
- Automated tests must prove that processing one BCS cannot modify another BCS database.

## Migration Strategy

The application will maintain a dedicated examination migration path. Approved orchestration commands will support:

```text
Migrate one examination database
Migrate all active examination databases
Inspect schema version
Detect pending examination migrations
```

Exact command names will be finalized during the infrastructure implementation phase.

## Backup and Archival Strategy

- Examination databases are backed up independently.
- A completed examination can be marked read-only or archived.
- Restoring one BCS must not require restoring all other BCS datasets.
- Central registry metadata must retain the examination database identity and lifecycle status.

## Review Trigger

This decision is reviewed only if:

- Infrastructure limitations prevent reliable per-examination databases.
- A future approved requirement demands high-volume cross-examination transactional processing.
- The organization mandates a different physical data-isolation standard.

Until superseded by another accepted ADR, ADR-001 governs all database and module design.
