# Preliminary Module Foundation

## Locked source contract

The import workbook contains exactly: `user`, `reg`, `mark`, `candidate_status`.

## Row policy

| Mark | Source status | Normalized status | Cut-off eligible | Warning |
|---|---|---|---|---|
| Present | Blank | ACTIVE | Yes | No |
| Present | Text | ACTIVE | Yes | Yes |
| Blank | Text | CANCELLED | No | No |
| Blank | Blank | CANCELLED | No | Yes |

An approved preliminary row links to `registrations.id` through `registration_id`. A registration without an approved preliminary row is derived as absent; no redundant attendance column is stored.

The module requires synchronized database and daily file audit logs for import, validation, approval, reconciliation, cut-off changes, finalization, reopening and manual correction.
