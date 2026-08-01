# Preliminary P2 — Fast Staging, Validation and Approval

P2 deliberately separates ingestion from validation. Large preliminary files are streamed once with OpenSpout into a lightly processed staging table. Validation then uses chunked registration lookups and bulk staging updates. Approval performs bulk `upsert()` into `preliminary_results`.

The approved source is a full snapshot, not an incremental patch. Missing registered candidates are intentionally not materialized as result rows; absence will be derived by reconciliation in P3.

The import remains traceable through `preliminary_import_batches`, staging rows, `source_batch_id`, database audit entries, and the `preliminary` daily log channel established in P1.
