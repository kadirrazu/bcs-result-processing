# Allocation A6 — Fast Report Source Verification

A6 is a reporting/publishing consumer of the already finalized and verified Allocation result. It must not repeat the expensive full Allocation upstream integrity chain for every TXT/XLSX/DOCX request.

The A6 publishing gate now verifies only its direct result authority:

1. latest A5 exists, is finalized, not stale and 100% PASS;
2. latest current A4 exists;
3. A5 is bound to that exact latest A4 run;
4. A5's stored A4 output hash matches the latest A4 output hash;
5. A5 candidate/capacity hashes exist;
6. every queued export stores A4/A5 run IDs and these hashes, and the worker rechecks the queued snapshot before file generation.

Result-affecting upstream changes remain the responsibility of the locked Allocation dependency/staleness mechanism, which marks A4/A5 stale and therefore blocks A6.

This removes duplicate full-chain hashing from both the HTTP queue action and the export worker. Queue submission should therefore return immediately apart from normal DB/job dispatch overhead, while the worker's source-verification phase should be a short metadata/hash comparison.
