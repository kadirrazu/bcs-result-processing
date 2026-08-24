# Choice Optimization CO4C2 — Historical Match Operator Review & Confirmation

## Review rules

- CO4C1 `MATCHED` rows are automatically confirmed and immediately usable as confirmed historical recommendations.
- CO4C1 `REVIEW` rows remain pending until an operator explicitly confirms or rejects them.
- Operator administrative reason is mandatory.
- A confirmed REVIEW row becomes `match_status=matched`, `resolution_status=operator_confirmed`.
- A rejected REVIEW row becomes `match_status=rejected`, `resolution_status=operator_rejected`.
- If several defensive competing repository rows exist for one current candidate, confirming one automatically rejects the remaining pending competing rows.
- Re-pull replaces the entire derived match snapshot, including prior operator resolutions.

## Audit

Operator decisions create Choice Optimization processing audit events:
- `HISTORICAL_MATCH_CONFIRMED`
- `HISTORICAL_MATCH_REJECTED`

Audit context records current registration, previous BCS/dataset/row, previous registration/name/father/cadre, decision and administrative reason.

## UI

- Historical source listing exposes `Review Next`, `Review`, `View`, `Operator Confirmed`, and `Rejected`.
- Individual Historical Match Review compares current vs previous identity evidence side-by-side.
- Save & Continue uses AJAX and advances to the next pending review; when none remains it returns to the source summary.

## Downstream incorporation boundary

`ChoiceOptimizationHistoricalRecommendationService` exposes only `match_status=matched` records. This is the authoritative workspace source for the later historical-cadre-based choice trimming phase.
