# Choice Validation — Import Performance & Progress Hotfix v2.0.1

Choice source staging now follows the established Preliminary/Viva operator pattern.

- XLSX worksheet dimension is read quickly before streaming, so `total_rows` is known early.
- Staging inserts remain bulk/chunked and `processed_rows` / `progress_percent` are updated after each chunk.
- The review page polls a lightweight JSON status endpoint and shows a progress bar instead of repeatedly reloading the full page.
- Chunk sizes are environment-configurable:
  - `CHOICE_STAGING_CHUNK_SIZE` (default 1000)
  - `CHOICE_VALIDATION_CHUNK_SIZE` (default 1000)
  - `CHOICE_APPROVAL_CHUNK_SIZE` (default 1000)
  - `CHOICE_VALIDATION_QUEUE` (default `imports`)
- No database migration is required.
