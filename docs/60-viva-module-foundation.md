# Viva Module Foundation (V1)

V1 establishes the Viva module without changing the locked Preliminary or Written workflows.

## Locked configuration

- Viva full mark: config-driven, default `100`
- Viva pass percentage: config-driven, default `50`
- Viva high-mark review: config-driven, default `80`
- Operational statuses: ACTIVE / CANCELLED / WITHHELD / EXPELLED
- Only ACTIVE candidates will participate in Viva academic processing.
- Warnings do not automatically exclude an ACTIVE candidate.

## Two source contracts

Candidate mapping:

`user, reg, code`

Board data:

`viva_date, member_id, code, mark, viva_cff, viva_em, viva_phc, invalid, issue`

The first four board fields are mandatory. The remaining five are optional source/review fields.

## Foundation tables

- `viva_import_batches`
- `viva_mapping_import_staging`
- `viva_candidate_mappings`
- `viva_board_import_staging`
- `viva_results`
- `viva_processing_states`
- `viva_processing_audits`

## Important boundaries

- Candidate mapping will validate against the current finalized Written-qualified population.
- `code` is text and preserves leading zeroes.
- Registration quota remains authoritative; Viva quota markings are supplemental review information.
- Merit Generation is outside the Viva Module.
- No public Viva mark/PASS-FAIL TXT or DOCX publishing workflow will be created.

V2 will implement the fast Candidate Mapping import, validation against finalized Written results, review, correction and approve/merge workflow.
