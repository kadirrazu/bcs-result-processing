# Choice Optimization CO4C3.3 — Vertical Choice Chips + Minimal Listing

- The shared choice chip now explicitly uses `inline-flex flex-column`, so sequence, code and abbreviation are guaranteed to render vertically:
  - sequence on top;
  - code in the middle;
  - abbreviation at the bottom.
- The same component is used by both Historical Choice Optimization listing and individual detail.
- Listing keeps `NO PREVIOUS BCS MATCH` / `NO HISTORICAL DATA` as compact status badges only.
- The explanatory reason for unchanged choice due to missing historical recommendation remains only on the individual detail page.
