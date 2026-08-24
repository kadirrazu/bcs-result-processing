# Choice Optimization CO4C3.2 — Stacked Candidate/Choice UI

- Historical Choice Optimization listing no longer uses a wide result table.
- Each candidate is rendered as a full-width stacked record:
  - top row: SL, current candidate identity, Previous BCS match context, system status;
  - full-width Input Effective Choice;
  - full-width Allocation-ready Choice;
  - cutoff/removed summary and detail action.
- Previous BCS match is explicitly shown as `BCS XX - CADRE`.
- Candidates with no confirmed historical recommendation show red `NO PREVIOUS BCS MATCH` / `NO HISTORICAL DATA` status and an explanatory no-change message.
- Choice chips now wrap naturally instead of forcing a horizontal scrollbar. Each chip remains vertical: sequence, code, abbreviation.
- Individual detail uses the same wrapped choice lanes and adds an Optimization Reason section.
