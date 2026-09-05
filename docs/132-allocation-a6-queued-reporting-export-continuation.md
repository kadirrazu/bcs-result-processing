# Allocation A6 — Queued Reporting/Export Continuation

## Locked continuation implemented

- TXT, XLSX and DOCX generation are queued through the centralized Allocation queue instead of running inside the browser request.
- `reporting_export_runs` is a reusable examination-level export-run ledger with status, phase, progress, source snapshot, parameters, output file metadata and failure state.
- The worker repeats strict Allocation readiness/integrity verification before generation and verifies the queued A5/A4 hash snapshot. Changed authority blocks publishing.
- Export progress is exposed by JSON status polling; the browser is never authoritative for processing.
- Completed artifacts remain privately stored and can be downloaded from the completed export-run page.

## TXT polish

- Header includes `TOTAL ALLOCATED = N` immediately after Generation Time.
- Cadres remain in A6/Circular order, including zero-allocation cadres.
- Empty cadre sections show `NO ELIGIBLE CANDIDATE` and `TOTAL = 0`.
- Every cadre section ends with `TOTAL = N`.
- The same zero-allocation behavior is preserved in cadre-wise TXT ZIP output.

## Dynamic Excel builder

- Existing predefined exports remain available.
- Custom builder supports module-wise selection from Registration, Preliminary, Written, Viva, Tabulation, Choice, Merit, Allocation and A5 validity.
- Choice fields include Registration, Validated, OMR and final Effective allocation-ready sequences.
- Exact selected field keys/labels and scope are persisted in export-run provenance and A6 export audit.

## Reuse boundary

Reusable infrastructure is deliberately separated under `App\Services\Reporting`:

- `ReportExportFileStore`: private output/source artifact storage.
- `SpreadsheetReportWriter`: generic XLSX mechanics (headers, row writing, identifier-as-text handling, freeze/filter/column sizing, progress callback).
- Existing `DocxPlaceholderTemplateService` remains the generic DOCX template-fill component.

Allocation-specific readiness, A5/A4 source binding, data-field catalogue, field resolution and business semantics remain under `App\Services\Allocation`.

This boundary is intended to allow the future dedicated Reporting Module to reuse queue/file/spreadsheet/template mechanisms without coupling itself to Allocation business rules.
