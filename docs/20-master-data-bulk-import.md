# Master Data Bulk Import

## Standard
Large CRUD directories provide search, 25/50/100 pagination, template download, spreadsheet preview and confirmed import.

## Supported formats
XLSX, XLS and CSV through PhpSpreadsheet. Templates are generated dynamically so headers remain synchronized with the application.

## Modes
- Insert: add new keys and skip existing keys.
- Update: update existing keys and skip new keys.
- Upsert: insert new keys and update existing keys.

## Safety
Imports are authorized by the model create policy, validated before mutation, cached for 30 minutes, confirmed explicitly and persisted in a transaction. Subject codes are normalized to uppercase strings; leading zeroes are preserved when the spreadsheet cell is text.

## Future extension
The import foundation is reusable for candidates, marks, posts, choices, circular rules and examination snapshots. Very large examination imports should later move confirmed execution to a queued job with an import-history table.
