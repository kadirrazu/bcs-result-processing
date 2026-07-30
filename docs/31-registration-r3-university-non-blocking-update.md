# Registration R3 — Non-blocking University Code Update

## Decision

University is optional registration metadata and is not used by the result-processing pipeline. Registration import must therefore not reject a row only because its university code is absent from the active university master.

## Import behaviour

| Source value | Master match | Persisted value | Outcome |
|---|---:|---|---|
| Blank | Not applicable | `NULL` | Imported without warning |
| Code exists | Yes | Original code | Imported without warning |
| Code does not exist | No | Original code | Imported with warning |

For an unknown code, the importer appends an idempotent comment:

```text
[IMPORT WARNING] Invalid University Code: <code>
```

The same warning is also stored in the import-row audit record and increments the batch warning count. Candidate `status` and `validation_status` remain unchanged because the issue is non-blocking.

## Future master-data resolution

The source code is deliberately retained in `registrations.university_code`. When the corresponding university is later added to the central master, CRUD screens and future reports resolve the name automatically without modifying registration rows.

## CRUD behaviour

The details screen displays an unmatched value as:

```text
<code> — Invalid University Code
```

The edit form retains that unmatched value as a selected option, so editing an unrelated field does not accidentally erase the imported source code.
