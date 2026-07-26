# Coding and Commenting Standards

## Status
Approved foundation standard. Changes require documentation review and an ADR when architectural impact exists.

## Objectives
- Keep business logic explicit, testable, and traceable.
- Prefer clarity over cleverness.
- Keep framework concerns outside domain rules.
- Synchronize code, tests, and documentation.

## PHP Standards
- Use PHP 8.3+ features conservatively and intentionally.
- Follow PSR-12 formatting.
- Enable strict typing in new non-framework PHP classes where practical.
- Use typed properties, parameter types, and return types.
- Prefer immutable DTOs for data crossing application boundaries.
- Avoid magic strings when an enum or named constant is appropriate.
- Do not catch exceptions merely to suppress them.

## Laravel Standards
- Controllers remain thin and coordinate HTTP concerns only.
- Validation belongs in Form Request classes.
- Authorization must be explicit through policies, gates, middleware, or Form Requests.
- Business operations belong in Actions and Services.
- Domain rules must not depend on HTTP requests, sessions, Blade, or controllers.
- Eloquent models represent persistence and relationships; they must not become orchestration services.
- Use database transactions around atomic state changes.
- Use queues only where retryability and operational visibility are designed.

## Documentation and PHPDoc
Public and reusable application classes should include concise PHPDoc describing intent, inputs, outputs, and important exceptions.

Required PHPDoc targets:
- Actions
- Services
- Domain rules
- DTOs
- Repositories and interfaces
- Console commands
- Non-obvious model scopes and casts

Do not repeat obvious type information already expressed by PHP signatures.

## Commenting Rules
Comments explain **why**, constraints, risks, or business intent.

Good comment:
```php
// Preserve the imported source value so the result can be reproduced after rule changes.
```

Avoid:
```php
// Set the status to passed.
$status = ResultStatus::Passed;
```

Complex processing code must include intent-based comments at decision boundaries, especially for:
- cut-off evaluation
- paper-crash handling
- combined-paper rules
- tie-breakers
- quota fallback
- allocation release and upgrade logic
- idempotency and retry protection

## Error Handling
- Throw domain-specific exceptions for expected business failures.
- Let unexpected exceptions reach centralized reporting after contextual logging.
- Never expose stack traces or sensitive data to users.
- User-facing messages must remain clear and actionable.
- Failed processing runs must retain enough metadata for diagnosis and restart.

## Logging
Logs must include useful context such as:
- examination identifier
- processing run identifier
- import batch identifier
- actor identifier
- candidate identifier where appropriate

Do not log passwords, secrets, full authentication tokens, or unnecessary personally identifiable data.

## Database Changes
- Every schema change requires a migration.
- Migrations must be reversible where safely possible.
- Examination database migrations must be compatible with the schema-version strategy.
- Destructive migrations require backup and rollback documentation.
- Application code must not depend on manually edited production schema.

## Testing Requirement
A feature is incomplete until its appropriate tests pass.

Minimum expectations:
- Feature tests for HTTP behavior and authorization.
- Unit tests for deterministic domain rules.
- Integration tests for critical database workflows.
- Regression tests for every confirmed processing defect.

## Prohibited Practices
- Business logic in Blade templates.
- Direct request access from Services or Domain Rules.
- Copying modules per BCS number.
- Dynamic table names such as `bcs47_candidates`.
- Silent data correction without audit evidence.
- Updating imported raw values to store derived values.
- Large unreviewed controllers or models used as service containers.
