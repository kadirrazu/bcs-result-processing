# Choice Validation Final Export Performance Hotfix v3.0.3

## Root cause

The original CV6/CV7 export path repeated expensive work:

- `summary()` was called,
- `finalizedVersion()` then verified the full SHA-256 dataset hash,
- `results()` called `finalizedVersion()` again and repeated the hash scan,
- `results()` eager-loaded every Registration and every `source.items` row,
- PDF only needed aggregates but loaded all candidates anyway,
- Excel AutoSize measured every cell across 14 columns.

For ~3,631 candidates and up to 20 source choices each, this can mean tens of
thousands of Eloquent models plus repeated hash passes, which appears like an
infinite export.

## Fix

- Export integrity verification is performed once through `verifiedSummary()`.
- PDF uses aggregate SQL only; it does not load candidate/source relations.
- Excel uses a flat DB query with 500-row chunks.
- Original choices are read from the already-preserved JSON `source_snapshot`;
  no mass `source.items` eager loading is required.
- Excel AutoSize is replaced with practical fixed widths.
- Formula pre-calculation is disabled because this workbook contains no formulas.
- Effective Choices After Manual Correction also omits redundant `Retained` text.

No schema, finalization rule, hash algorithm, or finalized-data contract changes.
