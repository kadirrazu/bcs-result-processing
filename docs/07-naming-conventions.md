# Naming Conventions

## General Principles
Names must describe business meaning, not implementation accidents. Use one term consistently across database, PHP, UI, tests, and documentation.

## PHP Classes
| Type | Convention | Example |
|---|---|---|
| Controller | Singular resource + `Controller` | `CandidateController` |
| Form Request | Verb + resource + `Request` | `StoreCandidateRequest` |
| Action | Imperative operation + `Action` | `ProcessPreliminaryResultAction` |
| Service | Capability + `Service` | `PreliminaryResultService` |
| Domain Rule | Business concept + `Rule` | `WrittenPassRule` |
| DTO | Business payload + `Data` | `PreliminaryCutoffData` |
| Repository | Entity + `Repository` | `CandidateRepository` |
| Exception | Business failure + `Exception` | `CutoffNotApprovedException` |
| Policy | Model + `Policy` | `UserPolicy` |
| Enum | Singular concept | `ResultStatus` |
| Command | Operation + `Command` | `MigrateExaminationDatabasesCommand` |

## Methods
- Use verbs for commands: `approveCutoff()`, `process()`, `publish()`.
- Use question form for booleans: `isApproved()`, `canPublish()`.
- Use `find` when absence is expected and nullable.
- Use `get` when a value must exist or a collection is returned.
- Use `create`, `update`, `delete`, `archive`, and `restore` consistently.

## Variables
Prefer explicit domain names:
```php
$examination
$processingRun
$preliminaryCutoff
$candidateResult
```

Avoid vague names:
```php
$data
$item
$obj
$temp
```
except in very small, obvious scopes.

## Database Tables
- Use plural snake_case names.
- Central tables use generic names: `users`, `examinations`, `designations`.
- Examination databases use the same generic schema: `candidates`, `preliminary_marks`, `written_results`.
- Do not prefix tables with BCS numbers.
- Pivot tables use singular names in alphabetical order unless domain clarity requires otherwise.

## Columns
- Foreign keys: `<entity>_id`.
- Timestamps: `<event>_at`.
- Booleans: `is_`, `has_`, or `can_` prefix.
- Counts: `_count` suffix.
- Status columns: explicit concept, such as `processing_status` or `publication_status`.
- Raw imported values: `raw_` prefix where normalized values also exist.
- Derived values must be distinguishable from source values.

## Enums and Statuses
Use stable machine values and user-friendly labels separately.

Example values:
```text
pending
running
completed
failed
cancelled
```

Do not store translated labels as database state.

## Routes
Use kebab-case URLs and conventional resource routes where CRUD semantics apply.

Examples:
```text
/examinations
/preliminary-cutoffs
/processing-runs/{processingRun}
```

Business operations use explicit action routes:
```text
/preliminary-cutoffs/{cutoff}/approve
/processing-runs/{run}/retry
/results/{result}/publish
```

## Tests
- Feature test: `ProcessPreliminaryResultTest`.
- Unit test: `WrittenPassRuleTest`.
- Test names describe behavior: `it_rejects_processing_without_an_approved_cutoff`.

## Documentation
Document filenames use numeric ordering and kebab-case. ADR identifiers remain stable after publication.
