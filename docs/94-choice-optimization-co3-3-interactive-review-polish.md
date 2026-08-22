# Choice Optimization CO3.3 — Interactive OMR Review Polish

Status: implemented refinement.

- OMR review shows original Registration category and finalized Written-qualified track.
- Choice comparison preserves three separate lineages: Registration Choice, finalized Validated Choice, and raw OMR Choice.
- Choice preferences are rendered left-to-right in authoritative preference order: #01, #02, ... up to the configured maximum (default #20). #01 is the first preference.
- Review-required decisions/corrections are saved asynchronously without a full-page redirect.
- A successfully resolved candidate fades out and the next review-required candidate is automatically focused.
- Re-validation is surfaced only after all operator-review items are resolved.
- Raw/source data remains preserved; operator decisions remain audited by the existing resolution services.
