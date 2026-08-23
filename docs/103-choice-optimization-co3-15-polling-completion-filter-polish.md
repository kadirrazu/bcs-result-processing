# Choice Optimization CO3.15 — Polling Completion & Filter Polish

- JSON polling continues during queued/running work.
- When a run transitions from running to finished, the page refreshes exactly once so server-rendered Processing Action, buttons, filters and row data reflect the completed state.
- No recurring full-page refresh is used.
- Added `Warning` filter for rows with one or more validation warnings.
- Added `Operator Confirmed` filter for rows with a saved registration resolution and/or OMR decision resolution.
