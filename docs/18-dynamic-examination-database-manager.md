# Dynamic Examination Database Manager

## Decision

The default Laravel connection remains the CENTRAL database. All operational BCS models extend `ExaminationModel` and use the runtime `exam` connection.

## Request lifecycle

1. A user selects an enabled, non-archived examination.
2. The system verifies that its physical database is reachable.
3. The examination ID is stored in the user's session.
4. Examination-domain routes run `EnsureExaminationSelected` and `ConfigureExaminationConnection`.
5. The manager clones the configured base connection, replaces only the database/schema name, and purges cached PDO state.
6. The connection is purged after the request to prevent cross-request leakage in long-running workers.

## Historical access

Completed and historical examinations remain in the central registry. Archived examinations are intentionally not selectable as writable contexts. A future read-only historical mode will permit reporting while blocking processing mutations.

## Security invariants

- Database names accept only letters, digits, and underscores.
- Credentials are inherited from environment configuration and are never stored in the examinations table.
- A failed connection never replaces the user's existing active examination.
- Central models never extend `ExaminationModel`.
