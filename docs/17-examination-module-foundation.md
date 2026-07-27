# Examination Module Foundation

## Scope

This milestone introduces the central examination registry and request-scoped examination selection.
It does **not** create, migrate, drop, or connect to physical examination databases yet.

## Central Registry

Each record identifies one BCS examination and its future physical database:

- `bcs_number`
- `name`
- `slug`
- `database_name`
- `status`
- `is_enabled`

No candidate, marks, merit, allocation, or tabulation data is stored in this central table.

## Examination Context

`ExaminationContext` stores the selected examination ID in the authenticated session and resolves the central registry model on demand.

Safety rules:

- disabled examinations cannot be selected;
- archived examinations cannot be selected;
- invalid/stale session selections are cleared automatically;
- the active examination cannot be disabled until another context is selected.

## Deferred Work

The next milestone will add:

1. dynamic connection configuration;
2. database connectivity verification;
3. examination database migration runner;
4. context-required middleware for examination-scoped modules.
