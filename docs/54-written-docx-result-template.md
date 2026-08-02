# Written DOCX Result Template Engine

This phase adds a publishing-document helper without changing Written result processing.

## Use
After the Written result is finalized, open **Fill Result Template**, upload a `.docx` notice/template and generate a new Word document.

Supported placeholders:

- `[[GG]]`, `[[TT]]`, `[[GT]]`, `[[ALL]]` — registration block plus total.
- `[[RESULT_GG]]`, `[[RESULT_TT]]`, `[[RESULT_GT]]`, `[[RESULT_ALL]]` — registration numbers only.
- `[[TOTAL_GG]]`, `[[TOTAL_TT]]`, `[[TOTAL_GT]]`, `[[TOTAL_ALL]]` — totals only.
- `[[EXAM_NAME]]` — selected examination name.
- `[[FINALIZED_DATE]]` — Written finalization date.

GG follows the effective Written grouping `GG + GN`; TT follows `TT + T`; GT contains only GT.

The source template is never overwritten. Generation is recorded in the Written audit trail and file log. Result calculation and TXT downloads are independent of this feature.

For the most predictable Word formatting, put large result placeholders on their own paragraph/line. Placeholders in table cells, headers and footers are also processed.
