# Action, Service, and Repository Boundaries

## Request Flow
```text
Route → Controller → Form Request → Action → Service / Domain Rules → Repository / Model → Database
```

## Controllers
Controllers own HTTP concerns only:
- receive validated input
- invoke authorization
- call one primary Action or query service
- return response, redirect, or view

Controllers must not:
- implement business calculations
- manage long transactions
- switch examination connections directly
- coordinate import or processing loops

## Form Requests
Form Requests own:
- syntactic validation
- request-specific authorization
- normalization that is safe and not business processing

They do not own:
- database workflows
- pass/fail decisions
- processing state transitions

## Actions
An Action represents one application use case.

Examples:
- `CreateUserAction`
- `ApprovePreliminaryCutoffAction`
- `ProcessPreliminaryResultAction`
- `PublishWrittenResultAction`

Action responsibilities:
- establish application context
- coordinate services and repositories
- enforce use-case authorization when not purely HTTP-bound
- manage the transaction boundary
- create audit or processing-run entries
- return a purposeful result object

An Action should normally expose one public method, commonly `execute()`.

## Services
Services provide reusable application capabilities or orchestrate a coherent sub-process.

Examples:
- `MarksDistributionService`
- `PreliminaryResultService`
- `ExaminationConnectionManager`
- `ProcessingRunService`

A Service must not become a generic dumping ground. Split it when its responsibilities no longer form one cohesive capability.

## Domain Rules
Domain Rules implement deterministic policy decisions.

Examples:
- `PreliminaryPassRule`
- `WrittenPaperCrashRule`
- `MeritTieBreaker`
- `QuotaPriorityRule`

Rules should prefer plain PHP inputs or DTOs and return explicit results. They must not access the HTTP request or render UI.

## Repositories
Repositories are used selectively, not automatically for every model.

Use a repository when:
- complex persistence queries are reused
- storage implementation needs isolation
- processing needs bulk or optimized operations
- dynamic examination connections must be safely encapsulated
- tests benefit materially from a persistence boundary

Direct Eloquent use is acceptable for simple, local CRUD inside a well-scoped Action or query class.

Repositories must not contain business policy. They answer persistence questions and perform persistence operations.

## Models
Models own:
- relationships
- casts
- local query scopes
- small state helpers
- persistence-aware invariants where appropriate

Models do not own:
- multi-step workflows
- report generation
- import orchestration
- cross-aggregate processing

## DTOs
DTOs carry validated, explicit data across boundaries.

Use DTOs when:
- an Action has a non-trivial payload
- data comes from more than one transport
- immutable processing input improves reproducibility
- method signatures would otherwise become long or ambiguous

## Transactions
The Action normally owns the transaction boundary.

Rules:
- Keep transactions as short as correctness permits.
- Avoid network calls inside database transactions.
- Avoid cross-database atomic assumptions.
- Use idempotency and reconciliation for central/examination synchronization.

## Dependency Direction
Allowed:
```text
HTTP → Application → Domain
Application → Infrastructure contracts
Infrastructure → Domain/Application contracts
```

Forbidden:
```text
Domain → Controller
Domain → Blade
Domain → HTTP Request
Domain → session state
```
