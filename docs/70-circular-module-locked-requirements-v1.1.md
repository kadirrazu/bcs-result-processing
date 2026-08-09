# Circular Module — Locked Requirements v1.1 Amendment

This amendment supersedes the earlier one-row-per-main-cadre assumption where applicable.

## Central reusable master structure

- **Cadre Master** stores reusable main cadre identity: code, abbreviation, cadre name EN/BN, default/main post name EN/BN, type, order, active state.
- **Sub Cadre Master** stores reusable globally unique child code under exactly one parent: optional abbreviation, post name EN/BN, order, active state.
- Sub Cadre does **not** duplicate cadre name; it resolves the parent Cadre Master name.
- Main and Sub codes share one logical namespace. A numeric code may not exist in both masters.
- Current completed Registration, Preliminary, Written and Viva modules do not consume Cadre Master at runtime; this master redesign is therefore isolated from their locked processing logic.

## Choice adaptability

Current examinations may contain parent/main codes only. If a finalized Circular contains eligible sub-cadres for that main code, Choice Validation expands the main choice into eligible sub codes in Circular `sub_serial` order.

Future examinations may directly contain a Sub Cadre code in candidate choices. The resolver accepts a code as Main if it exists in Cadre Master, Sub if it exists in Sub Cadre Master, otherwise invalid. No PARENT_ONLY/DIRECT_ONLY/BOTH flag is required.

## Circular import sheet

Single sheet; names are not imported because they resolve from Master Data.

`cadre_serial, sub_serial, cadre_code, sub_cadre_code, cadre_type, post_count, bachelor_subject_codes, prs_codes, status, note`

- `sub_cadre_code` and `sub_serial` are blank when not applicable.
- Multiple bachelor/PRS codes use pipe `|`.
- New approved Excel fully replaces/synchronizes the effective Circular dataset.
- Master cadre type is authoritative; mismatch or inactive master reference is blocking.
- GG rows have no bachelor/PRS restriction.
- TT eligibility later uses Registration bachelor subject IN allowed set **AND** Registration PRS IN allowed set.

## Authority workflow

Circular finalization requires current effective data, Authority Preview PDF, explicit confirmation, versioned confirmation and confirmation notes. Any later effective-data change invalidates the prior preview/confirmation and marks downstream Choice Validation and later applicable stages stale.
