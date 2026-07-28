# Master Data Export and Serial Update

## Scope

The three central master directories now expose a global serial number and complete-table export actions.

## Serial numbering

The list pages calculate the serial from the paginator's `firstItem()` plus the current loop index. The number therefore continues correctly on page 2 and later pages.

## Excel export

Excel exports contain all records, not only the current page or current search result. Headers intentionally match the import templates so an exported workbook can be edited and imported again.

## PDF export

- Paper: A4
- Margin: 0.5 inch on every side
- Cadre Masters: landscape
- Subject masters: portrait
- Header: master table name
- Metadata: generation timestamp
- Repeated column headings on subsequent pages

## Authorization

Export endpoints use each master model's existing `viewAny` policy.
