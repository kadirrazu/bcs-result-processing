# Choice Validation CV5 Candidate Detail Visual Review v2.2.5

Candidate detail review is expanded without changing Choice Validation business
rules.

## Candidate context

The summary now displays:
- Registration / User ID in one column
- Candidate Name in the next column
- Original Category from Registration (`cadre_category`)
- Derived Category after Written from finalized `written_qualified_track`
- Current Track resolved by Choice Validation (`general`, `technical`, `both`)

## Choice comparison

Three source/result layers remain distinguishable:
1. Original Imported Choices — immutable raw source
2. Effective Choices After Manual Correction — only shown when different
3. Validated Choices — actual validation output

Visual conventions:
- Retained: green
- Removed: red
- Expanded / derived: blue
- Manually corrected: yellow

## Resolution Trail

Rows with material changes are highlighted:
- removed -> `table-danger`
- expanded -> `table-info`
- retained -> neutral row

This is a UI/review enhancement only. No schema or validation algorithm changes.
