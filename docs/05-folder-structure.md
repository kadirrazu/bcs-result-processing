# Folder Structure and Namespace Convention

## 1. Status

- **Status:** Active baseline
- **Purpose:** Define where application responsibilities belong before feature implementation begins.

## 2. Principles

- Organize code by responsibility and business capability.
- Keep framework entry points thin.
- Do not create BCS-number-specific namespaces.
- Prefer explicit use-case names over generic manager classes.
- Introduce abstraction only when it provides clear value.
- Keep deterministic business rules easy to unit test.

## 3. Approved Application Structure

```text
app/
├── Actions/
│   ├── Administration/
│   ├── Examinations/
│   ├── Preliminary/
│   ├── Written/
│   ├── Tabulation/
│   ├── Merit/
│   ├── Choices/
│   └── Allocation/
├── Console/
│   └── Commands/
├── Domain/
│   ├── Preliminary/
│   │   ├── Rules/
│   │   ├── ValueObjects/
│   │   └── Exceptions/
│   ├── Written/
│   ├── Merit/
│   ├── Choices/
│   └── Allocation/
├── DTO/
│   ├── Preliminary/
│   ├── Written/
│   ├── Processing/
│   └── Reports/
├── Enums/
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
├── Infrastructure/
│   ├── Database/
│   │   ├── Examination/
│   │   ├── Connections/
│   │   └── Migrations/
│   ├── Import/
│   ├── Reporting/
│   └── Allocation/
├── Jobs/
├── Listeners/
├── Models/
│   ├── Central/
│   └── Examination/
├── Policies/
├── Queries/
├── Repositories/
│   └── Contracts/
├── Services/
│   ├── Examinations/
│   ├── Preliminary/
│   ├── Written/
│   ├── Processing/
│   ├── Reporting/
│   └── Allocation/
└── Support/
```

Directories are introduced when implementation requires them; empty directories do not need to be created in advance.

## 4. Central and Examination Models

```text
app/Models/Central/
app/Models/Examination/
```

### Central Models

Examples:

- `User`
- `Designation`
- `Examination`
- `SystemSetting`

### Examination Models

Examples:

- `Candidate`
- `PreliminaryMark`
- `PreliminaryResult`
- `WrittenMark`
- `Tabulation`
- `MeritResult`
- `Allocation`

Examination models must use the controlled examination connection and must not choose a database using table-name concatenation.

## 5. Actions

An Action name uses a business verb and one clear use case:

```text
CreateUserAction
UpdateUserAction
ImportPreliminaryMarksAction
ApprovePreliminaryCutoffAction
GeneratePreliminaryResultAction
GenerateWrittenResultAction
GenerateGeneralMeritAction
RunAllocationAction
```

Avoid vague names such as:

```text
UserManager
ProcessingHandler
DataHelper
CommonService
```

## 6. Services

Services are grouped by business capability and named for reusable behavior:

```text
PreliminaryDistributionService
PreliminaryResultProcessor
WrittenRuleEvaluationService
MeritRankingService
ProcessingRunService
ExaminationConnectionManager
```

A Service must not accept an HTTP Request object.

## 7. Domain Rules

Domain rules live under the relevant capability:

```text
app/Domain/Written/Rules/MandatoryPaperRule.php
app/Domain/Written/Rules/PaperCrashRule.php
app/Domain/Written/Rules/CombinedPaperRule.php
app/Domain/Merit/Rules/MeritTieBreaker.php
```

Rule classes should be deterministic and focused. They may consume value objects or DTOs rather than framework requests.

## 8. DTOs

DTOs carry validated, explicit data between boundaries.

Examples:

```text
PreliminaryCutoffData
ProcessingRunSummaryData
WrittenCandidateMarksData
MeritCandidateData
ReportParametersData
```

DTOs should not contain persistence side effects.

## 9. Queries

Reusable and complex read operations belong in `app/Queries` when a named query improves clarity.

Examples:

```text
PreliminaryDistributionQuery
WrittenPassedCandidatesQuery
TechnicalMeritCandidatesQuery
OpenPostsQuery
```

Simple one-off Eloquent reads may remain inside an Action or repository where appropriate.

## 10. Repositories

Repositories are not mandatory for every model.

Use a repository when:

- Persistence spans multiple tables.
- A stable domain-oriented contract is useful.
- Query implementation is complex or reused.
- Infrastructure details should be hidden from the application layer.

Do not create pass-through repositories that merely repeat Eloquent methods without adding meaning.

## 11. Infrastructure

Infrastructure contains framework- and vendor-facing implementations, including:

- Examination connection management.
- Import readers and parsers.
- Report rendering.
- Allocation engine adapter.
- Database migration orchestration.

Domain rules must not depend directly on infrastructure implementations.

## 12. Database Migrations

```text
database/migrations/
    Central database migrations

database/migrations/examination/
    Shared examination-schema migrations
```

Central migrations and examination migrations are executed through separate controlled paths.

No migration file is named specifically for BCS 47, BCS 48, or another examination unless it represents a one-time formally approved data correction rather than schema design.

## 13. Routes and Controllers

Routes are organized by capability and protected by authentication and authorization.

Controllers remain under:

```text
app/Http/Controllers/
```

Suggested capability grouping:

```text
Administration/
Examinations/
Preliminary/
Written/
Merit/
Allocation/
Reports/
```

A controller should invoke a primary Action and return the resulting response.

## 14. Tests

```text
tests/
├── Unit/
│   └── Domain/
├── Feature/
│   ├── Administration/
│   ├── Examinations/
│   ├── Preliminary/
│   ├── Written/
│   ├── Merit/
│   └── Allocation/
└── Integration/
    ├── ExaminationDatabase/
    ├── Processing/
    └── Allocation/
```

Critical rules require unit tests. Examination database switching and isolation require integration tests.

## 15. Documentation

All engineering documents remain under `docs/`.

Architecture, business requirements, implementation, database schema, and automated tests must remain synchronized.

## 16. Prohibited Patterns

- `Bcs47CandidateController`, `Bcs48CandidateController`, and similar duplicated application classes.
- Dynamic table names constructed from BCS numbers.
- Business logic in Blade templates.
- Processing loops in controllers.
- Direct use of session state inside domain rules.
- Generic dumping grounds such as `Helpers.php` for domain behavior.
- Silent cross-database queries without an examination context.
