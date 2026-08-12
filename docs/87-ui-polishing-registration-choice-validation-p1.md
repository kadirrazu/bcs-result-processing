# UI Polishing P1 — Registration and Choice Validation

This polishing patch is based on the full project checkpoint completed through
Choice Validation.

## Registration

All Registration UI fields backed by a central code master follow the visible
`CODE - Title` convention where applicable. This includes candidate details,
manual-entry/edit dropdowns, list filters and relevant list columns.

The Registration landing page now includes equal-height summary cards for the
actual Registration-level categories/statuses supported by the model:

- Total Candidates
- GG
- TT
- GT
- Active
- Cancelled
- Withheld
- Invalid Validation

`Expelled` is intentionally not introduced at Registration level because the
current Registration lifecycle enum contains only Active, Cancelled and
Withheld. Expelled remains a Preliminary/Written/Viva processing status.

## Choice Validation

The Choice Source Excel Import area is simplified into one operator-oriented
card. Source rules are shown as compact badges/hints, while file selection and
the Upload & Stage action use one clean responsive control row.

No schema or business-rule changes are included.
