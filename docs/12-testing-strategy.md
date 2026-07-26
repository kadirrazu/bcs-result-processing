# Testing Strategy

## Status

**Approved — Foundation v1.0**

## Purpose

Automated tests are release controls, not optional examples. A business rule is not complete until its expected behavior and principal failure paths are covered by tests.

## Test Layers

1. **Unit tests** cover pure domain rules, value objects, calculations, parsers, and deterministic transformations.
2. **Feature tests** cover HTTP authorization, validation, application actions, persistence, and user-visible workflows.
3. **Integration tests** cover database boundaries, import adapters, queues, files, and examination-context switching.
4. **Regression tests** preserve confirmed production rules and previously corrected defects.

## Mandatory Coverage

Each module must test:

- permitted and forbidden actors;
- valid and invalid input;
- successful processing;
- important edge cases;
- idempotency where processing may be repeated;
- transaction rollback for failed writes;
- examination-data isolation for examination modules;
- audit/run records for controlled processing.

## Authentication Foundation

The current baseline verifies:

- inactive users cannot authenticate;
- active users can authenticate;
- successful login updates `last_login_at`;
- only administrators can manage application users.

## Commands

```bash
php artisan test
php artisan test --testsuite=Feature
php artisan test --filter=UserAuthorizationTest
```

All tests must pass before a Git commit is treated as complete.
