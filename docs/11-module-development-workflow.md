# Module Development Workflow

## Standard Lifecycle
```text
Requirement → Documentation → Data Design → Action/Service Design → Implementation → Tests → Verification → Documentation Update → Git Commit
```

## Step 1: Requirement Baseline
Document:
- business objective
- actors and permissions
- inputs and outputs
- workflow states
- edge cases
- audit and reporting needs
- failure and restart behavior

## Step 2: Impact Classification
Classify the change as:
- architecture-level
- database/workflow-level
- module-level business rule
- presentation/report-only

Architecture changes require an ADR.

## Step 3: Data Design
Define:
- central or examination database ownership
- tables and relations
- source vs derived fields
- constraints and indexes
- migration and rollback strategy
- data retention and history policy

## Step 4: Application Design
Define:
- Form Requests
- Actions
- Services
- Domain Rules
- DTOs
- repositories where justified
- policies and permissions
- processing run and audit integration

## Step 5: Implementation
Implement in small reviewable increments. Avoid mixing unrelated refactors with feature delivery.

## Step 6: Tests
Create tests before considering the module complete:
- authorization tests
- validation tests
- happy path
- boundary and edge cases
- failure handling
- idempotency or retry behavior where applicable
- regression cases

## Step 7: Verification
Run as applicable:
```bash
php artisan optimize:clear
php artisan test
npm run build
```

Also verify migrations and the relevant UI or console operation.

## Step 8: Documentation Synchronization
Update:
- requirements baseline
- architecture or ADR when needed
- database documentation
- module workflow
- changelog

## Step 9: Commit
A commit must be focused and leave the project in a working state.

Suggested commit format:
```text
<type>(<scope>): <summary>
```

Examples:
```text
docs(architecture): define application layer boundaries
feat(preliminary): add cut-off approval workflow
test(written): cover combined-paper pass rule
fix(allocation): restore post count after candidate upgrade
```
