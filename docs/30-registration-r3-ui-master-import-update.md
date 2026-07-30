# Registration R3 UI and Master Import Update

## Locked changes

- Division, district and university central masters support Excel template, preview and import.
- Registration Excel no longer contains a `division` column.
- `division_code` is derived from the active district master for every imported candidate.
- Manual registration also derives division from district on the server; the UI only displays the mapped division.
- GG (`cadre_category = 1`) disables and clears post-related subject in the form. The server-side normalizer remains authoritative.
- University is optional during registration import. Blank values persist as `NULL`; unmatched source codes are preserved and reported as non-blocking warnings.

## Import order

1. Divisions
2. Districts (each row must reference an existing active division code)
3. Universities
4. Registrations
