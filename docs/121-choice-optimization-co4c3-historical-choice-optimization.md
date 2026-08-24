# Choice Optimization CO4C3 — Historical Recommendation Choice Optimization

## Locked processing rule

Input is the current effective Choice Optimization choice snapshot when available; otherwise finalized Validated Choice is used. Upstream rows are never overwritten.

Only confirmed historical recommendations (`match_status=matched`) participate.

For every current candidate:
1. Resolve each historical cadre abbreviation against exact current Cadre/Sub-Cadre Master identity.
2. Require that exact resolved code exists in the finalized current Circular.
3. Find the resolved code in the current effective choice sequence.
4. When several historical cadres match, choose the smallest current choice index (highest current preference).
5. The matched cutoff choice and every lower preference are removed.
6. The remaining sequence becomes Allocation-ready Choice.

## Edge cases

- Pending Historical `REVIEW` blocks processing.
- `UNRESOLVED_HISTORICAL_CADRE`: warning, no trimming for that recommendation.
- `HISTORICAL_CADRE_NOT_IN_CURRENT_CIRCULAR`: warning, no trimming.
- `NO_MATCHING_CURRENT_CHOICE`: warning/no-op.
- `AMBIGUOUS_HISTORICAL_CADRE_MAPPING`: blocking issue; automatic trim is not performed and finalization is blocked.
- Parent→sub-cadre inference is not performed. Exact historical cadre/sub-cadre identity is required.
- Duplicate historical recommendations remain visible in audit/display but do not change the smallest-index cutoff rule.
- Cutoff at #01 produces `NO_HIGHER_CHOICE_REMAINS` and an empty Allocation-ready Choice.
- A valid trim produces `OPTIMIZED`; otherwise the row is `UNCHANGED`.

## Authority and staleness

CO4C3 output is a separate derived table.

Processing stores hashes for:
- input effective/validated choice snapshot;
- finalized Choice Validation dataset;
- finalized Circular dataset;
- Historical pull/match/resolution snapshot;
- CO4C3 output dataset.

Finalization re-verifies all hashes.

Historical Pull/Re-pull or operator match resolution marks produced CO4C3 output stale. Re-processing is mandatory before finalization/downstream Allocation.
