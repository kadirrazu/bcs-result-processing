# System Architecture

## 1. Document Status

- **Status:** Active
- **Decision authority:** Approved project architecture baseline
- **Applies to:** All application modules, processing stages, reports, commands, jobs, and integrations

## 2. Architectural Goal

The system uses one shared Laravel codebase to process multiple BCS examinations while preserving strict physical isolation of examination-specific operational data.

The architecture must support correctness, auditability, reproducibility, restartable processing, controlled approvals, and long-term maintenance without creating separate application code for each BCS.

## 3. Core Architectural Principles

1. **Business correctness before convenience.**
2. **Generic application code, isolated examination data.**
3. **Thin HTTP controllers.**
4. **Explicit use-case Actions.**
5. **Reusable Application Services.**
6. **Domain rules isolated from HTTP and presentation concerns.**
7. **Repositories only where they add meaningful abstraction.**
8. **Raw source data remains traceable.**
9. **Calculation, approval, and publication are separate states.**
10. **Long-running processes are idempotent, restartable, observable, and auditable.**
11. **No examination-specific table prefixes in the shared application database.**
12. **No separate controllers or services per BCS unless the business capability itself differs.**

## 4. High-Level Deployment Architecture

```text
Users / Operators
        │
        ▼
Laravel 13 Application
        │
        ├── Central Application Database
        │     ├── users and authorization
        │     ├── designations
        │     ├── examination registry
        │     ├── global master data
        │     ├── system settings
        │     └── central operational summaries
        │
        └── Selected Examination Database
              ├── candidates
              ├── preliminary data
              ├── written and viva data
              ├── tabulation
              ├── merit
              ├── choices
              ├── allocation
              ├── processing runs
              └── examination audit data
```

## 5. Examination Data Isolation

Each BCS examination has a separate physical database, for example:

```text
bcs_exam_47
bcs_exam_48
bcs_exam_49
```

Each examination database follows the same approved schema contract. Operational records from different BCS examinations must not be mixed in the same candidate, marks, tabulation, merit, or allocation tables.

The central database stores the examination registry and identifies the database connection details or logical database key for each examination.

## 6. Request and Use-Case Flow

```text
Route
  ↓
Controller
  ↓
Form Request
  ↓
Application Action
  ↓
Application Service
  ↓
Domain Rule / Policy
  ↓
Repository, Query Object, or Model
  ↓
Database
```

### 6.1 Controller

A controller:

- Accepts the validated request.
- Resolves the selected examination context where required.
- Invokes exactly one primary use case.
- Returns a response, redirect, view, or resource.

A controller must not contain business calculations, processing loops, persistence orchestration, or rule evaluation.

### 6.2 Form Request

A Form Request:

- Validates transport-level input.
- Performs the authorization entry check where appropriate.
- Normalizes simple request values when necessary.

It must not execute the business use case.

### 6.3 Application Action

An Action represents one user-visible or system-visible use case, for example:

- `ImportPreliminaryMarksAction`
- `ApprovePreliminaryCutoffAction`
- `GeneratePreliminaryResultAction`
- `GenerateWrittenResultAction`
- `GenerateGeneralMeritAction`

An Action coordinates services, transactions, authorization-sensitive state transitions, and output DTOs.

### 6.4 Application Service

A Service implements reusable multi-step application behavior that may be used by more than one Action, command, or job.

Services must not depend on HTTP requests, controllers, Blade views, or session state.

### 6.5 Domain Rule

A Domain Rule expresses deterministic business logic, such as:

- Preliminary pass threshold evaluation.
- Mandatory-paper absence evaluation.
- Paper-crash calculation.
- Combined-paper pass evaluation.
- Merit tie-break comparison.
- Quota priority selection.

Where practical, domain rules should be testable without a database.

### 6.6 Repository and Query Objects

Repositories are used only when they provide meaningful value, including:

- Complex persistence behavior.
- Stable domain-oriented data access contracts.
- Repeated query logic requiring isolation.
- Database implementation details that should not leak into Actions or Services.

Simple model persistence does not automatically require a repository.

## 7. Examination Context

Every examination-specific operation must execute inside an explicit `ExaminationContext`.

The context is responsible for:

- Identifying the selected examination.
- Resolving the correct examination database connection.
- Preventing examination-specific queries when no examination is selected.
- Exposing the examination identity to processing runs and audit records.
- Resetting connection state safely between requests, jobs, and tests.

The current examination must never be inferred from an uncontrolled global variable or table-name string concatenation.

## 8. Database Ownership

### 8.1 Central Database Owns

- Users, roles, permissions, and designations.
- Examination registry.
- Shared reference/master data approved for all examinations.
- Global system configuration.
- Central monitoring and operational summaries.
- Cross-examination report definitions where required.

### 8.2 Examination Database Owns

- Candidates.
- Examination-specific subject and rule snapshots where required.
- Imports and staging data.
- Preliminary marks, cut-offs, and results.
- Written and viva marks.
- Tabulation results.
- Merit results.
- Candidate choices.
- Post snapshots and allocation results.
- Processing runs, checkpoints, validation issues, and examination audit events.

A critical processing transaction should normally update only one examination database.

## 9. Processing Architecture

All consequential processing follows this pattern:

```text
Prerequisite validation
→ Processing-run creation
→ Input/configuration snapshot
→ Chunked execution
→ Checkpoint and progress recording
→ Validation and error isolation
→ Completion summary
→ Review or approval
→ Publication when authorized
```

### 9.1 Required Properties

Processing must be:

- Idempotent where feasible.
- Safe to retry.
- Protected from accidental duplicate execution.
- Chunked for large datasets.
- Transactional at appropriate boundaries.
- Observable through status and logs.
- Reproducible from recorded input and rule versions.

### 9.2 State Separation

The following states are independent:

```text
Processing state
Approval state
Publication state
```

A completed process is not automatically approved. An approved result is not automatically published.

## 10. Reporting Architecture

Reports are classified as:

- **Analytical reports:** Used for review and decisions, such as preliminary mark-frequency and cumulative reports.
- **Operational reports:** Used to identify errors, progress, and unresolved records.
- **Official result reports:** Versioned outputs prepared after approval.
- **Audit reports:** Explain who performed an action and which inputs and rules were used.

Report generation must use explicit parameters and must record the examination, data version, processing run, and generation time where applicable.

## 11. Integration Boundaries

The existing allocation engine remains a protected domain asset. It will be integrated through an adapter contract.

The application will:

- Prepare validated input in the engine's expected form.
- Execute the engine through a stable interface.
- Capture engine outputs and diagnostics.
- Apply persistence and audit behavior around the engine.
- Maintain regression tests proving existing allocation behavior.

The allocation algorithm will not be redesigned as part of ordinary integration work.

## 12. Dependency Rules

Allowed direction:

```text
Presentation → Application → Domain
Application → Infrastructure
Infrastructure → Framework / Database
```

Forbidden dependencies include:

- Domain rules depending on Controllers, Requests, Blade, session, or route helpers.
- Services reading directly from HTTP requests.
- Models making authorization decisions.
- Views triggering processing.
- Examination-specific code constructing dynamic table names.
- Central services silently querying an examination database without context.

## 13. Transaction Boundaries

Transactions are required for consequential multi-record changes, including:

- Approved cut-off activation.
- Result generation batch commits.
- Merit finalization.
- Allocation updates and post-balance changes.
- Publication state transitions.

Cross-database distributed transactions are not assumed. Cross-database synchronization must be explicit, idempotent, and recoverable.

## 14. Security Architecture

- Authentication is provided by Laravel Fortify.
- Authorization is enforced through policies, gates, permissions, or explicit use-case checks.
- Route middleware alone is insufficient for sensitive operations.
- Inactive users must not authenticate or continue privileged work.
- Sensitive actions require audit records.
- Examination selection does not itself grant permission to process that examination.

## 15. Testability Requirements

The architecture must support:

- Unit tests for deterministic domain rules.
- Feature tests for controllers, requests, policies, and Actions.
- Integration tests for examination connection switching.
- Processing tests for restartability and idempotency.
- Regression tests for merit and allocation outputs.
- Database isolation tests proving one BCS cannot modify another BCS database.

## 16. Change Control

A material architecture change requires:

1. Requirement or operational justification.
2. Impact analysis.
3. A new or superseding Architecture Decision Record.
4. Documentation update.
5. Tests proving the new invariant.
6. Controlled migration and deployment plan.
