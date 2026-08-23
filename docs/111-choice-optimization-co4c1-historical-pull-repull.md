# Choice Optimization CO4C1 — Historical Pull / Re-pull

## Architecture

The central Previous BCS Repository remains global. The current examination workspace keeps one current derived snapshot per Previous BCS.

- `Pull`: first search against that BCS's current EFFECTIVE central dataset.
- `Re-pull`: search again against the current EFFECTIVE central dataset and replace the old workspace match snapshot.
- Row-level pull history is not versioned repeatedly.
- Lightweight audit metadata remains in Choice Optimization processing audit and the source status row.

## Current-candidate scope

Only Written-qualified current candidates are searched:
- Written result status `active`;
- `written_qualified_track` is not null;
- Written module must be finalized and not stale.

## Match authority

Primary exact identity:
- SSC roll;
- SSC year;
- current Registration birth date vs historical `b_date`.

Supporting evidence:
- normalized candidate name;
- NID when both sides contain it;
- HSC roll/year when both sides contain them;
- historical optional secondary `dob`;
- father/mother/district retained as comparison evidence.

A unique exact primary identity becomes `MATCHED` only when normalized name is exact and no NID/HSC/secondary-DOB mismatch exists.

It becomes `REVIEW` when:
- normalized name is partial/different;
- NID/HSC/secondary DOB conflicts;
- defensive multiple primary-core matches exist.

Operator confirmation of REVIEW cases is CO4C2.

## Source version awareness

The workspace snapshot stores central dataset id, version and hash. If the central EFFECTIVE version changes, the Choice Optimization landing page shows `UPDATE AVAILABLE`; Re-pull replaces the old workspace snapshot.
