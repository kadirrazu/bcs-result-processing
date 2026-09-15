# Merit Generation — Revised Dependency, Cadre Merit and Tie Review

Locked 2026-09-15.

## Workspace sequence
Registration → Preliminary → Written → Viva → Tabulation → Circular → Merit → Choice Validation → Choice Optimization → Allocation → Reporting.

Circular may be prepared earlier operationally, but it must be finalized/current before Merit because Technical cadre eligibility is a Merit authority.

## Merit outputs
- Common Merit Position: unchanged.
- General Merit Position: unchanged.
- Technical Merit Position: unchanged.
- General cadre-wise Merit: removed from Merit Generation. General cadre competition is obtained when needed by filtering the applicable population and sorting `general_merit_position ASC`.
- Technical cadre-wise Merit: retained. It is derived from Technical Merit + surviving technical track + finalized Circular bachelor/PRS eligibility. Candidate choice is not a Merit-generation condition.

## Choice independence
Merit depends only on finalized/current Tabulation and finalized/current Circular. Choice Validation is not a Merit dependency and a Choice Validation change must not stale Merit. OMR remains in Choice Optimization. Final Allocation Ready Choice determines where a candidate actually competes during Allocation.

## Tie Review
The Commission business ranking rules are:
1. Grand Total DESC
2. Written Total DESC
3. Preliminary Mark DESC
4. DOB ASC (older first)

If two or more candidates remain tied after those four rules, the case is included in non-blocking Merit Tie Review. This applies only to Common, General and Technical Merit. It does not apply to Technical cadre-wise Merit because cadre-wise ordering preserves Technical Merit relative order.

Graduation Year, Registration/Roll and stable identity remain deterministic fallbacks so final Merit positions stay unique sequential values. Tie Review has UI plus PDF/XLSX export.

## Stale propagation
- Registration/Preliminary/Written/Viva changes continue to stale Tabulation; Merit becomes stale through the Tabulation authority change (and existing direct safety propagation where present).
- Circular changes stale Merit and Choice Validation/Choice Optimization as applicable.
- Choice Validation changes stale Choice Optimization, but no longer stale Merit.
- Final Allocation Ready Choice changes do not stale Merit; they stale Allocation input/downstream according to the existing Allocation chain.

## Allocation stale coupling clarification — 2026-09-15 audit
Allocation A2 is an immutable frozen intersection of current Merit + Final Allocation Ready Choice + the other direct authorities. Therefore any direct authority change after A2 exists marks that A2 authority stale and marks produced A3/A4/A5 lineage stale without deleting historical evidence. In particular, a new finalized Merit authority or a new finalized Choice Optimization authority invalidates any older frozen Allocation input. A6 remains lightweight and safe because A5/A4 are eagerly marked stale by this coupling rather than requiring A6 to re-hash the full upstream chain.

Choice Validation changes continue to leave Merit current. They stale Choice Optimization and any already-frozen Allocation authority, because the choice side must be reprocessed before Allocation can be current again.
