# Dynamic Query Builder Foundation

Dynamic Query Builder is a core cross-module capability of `/reports`. It is not part of Research & Statistics Section Reporting.

## Locked architecture
- Operators never see database tables, columns, join keys, SQL, or database functions.
- Operators may use only semantic fields registered by the application.
- Backend controls source relationships, joins, operators, sorting, grouping and aggregate eligibility.
- Preview is bounded to 5/10/20 rows, default 10, with a separate matching-count query.
- Query execution resolves finalized/current module authority before joining finalized module outputs.
- Report query configuration and report presentation configuration are separate concepts.

## Foundation slice
- Reporting hub entry.
- Dedicated Dynamic Query Builder page.
- Semantic field registry for Registration, Merit and Allocation proof-of-concept fields.
- Controlled authority resolver and whitelisted compiler.
- Cross-module live count and bounded preview.
- Conditions and sorting.
- Detail/Summary report modes with controlled grouping and aggregate execution (COUNT, COUNT DISTINCT, SUM, AVG, MIN, MAX where field metadata permits).
- Custom report title and per-column display labels.
- Serial/page-number/generation-timestamp presentation settings retained in the client definition for future export/persistence.

## Next slices
- Nested AND/OR condition groups.
- Multi-sort priority UI and multi-group interaction UI.
- Saved report definitions, audit/history, presets.
- Queue-backed XLSX/CSV/PDF exports and full report presentation renderer.
- Additional semantic modules and derived fields.
