# Database Design Principles

## Database Topology
The platform uses:
- one central application database
- one physical examination database per BCS examination

Multiple BCS operational datasets must never share the same candidate, marks, tabulation, merit, or allocation table.

## Central Database Responsibilities
Examples:
- users and authorization
- designations
- examination registry
- global master data
- application settings
- central operational summaries
- global audit references where appropriate

## Examination Database Responsibilities
Examples:
- candidates
- preliminary marks and results
- cut-off history
- written marks and results
- viva marks
- choices
- tabulation
- merit
- allocation
- import batches
- validation errors
- processing runs and local audit details

## Shared Schema Contract
Every examination database follows a managed schema contract. The application must know each database's schema version before processing.

## Source vs Derived Data
Source data and derived results must remain distinguishable.

- Imported source rows remain traceable.
- Normalized values may be stored separately.
- Result rows reference the rule/configuration version used.
- Reprocessing must not destroy historical evidence.

## Keys and Constraints
- Prefer numeric surrogate primary keys unless a stronger domain key is justified.
- Preserve business identifiers such as registration number with unique constraints scoped to the examination database.
- Add foreign keys where they improve integrity and operational requirements permit them.
- Add unique indexes for idempotency keys and import identity.

## Status Separation
Keep these concepts separate:
- data validation status
- processing status
- approval status
- publication status

Do not overload one generic `status` column for unrelated state machines.

## Auditability
Critical records must identify:
- actor
- source/import batch
- processing run
- rule/configuration version
- creation and update time

## Performance
- Index fields used in joins, filters, ordering, and uniqueness checks.
- Process large datasets in chunks or set-based SQL operations.
- Avoid loading full examination datasets into memory without a measured reason.
- Report queries must be reviewed for index coverage.

## Archival
Completed examination databases may be moved to read-only or archival infrastructure. The examination registry must retain their location and availability state.
