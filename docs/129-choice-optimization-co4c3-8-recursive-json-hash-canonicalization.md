# Choice Optimization CO4C3.8 — Recursive JSON Hash Canonicalization

## Root cause

CO4C3 output rows contain nested JSON objects such as:
- historical recommendations;
- matched cutoff;
- warnings;
- blocking issues.

Processing-time PHP arrays preserve insertion order. MySQL's JSON storage can normalize object key order. After reading the exact same logical JSON back through Eloquent, associative object keys can therefore appear in a different order.

`json_encode()` is key-order-sensitive, so the pre-insert and post-database serialized byte streams differed even when the logical data was identical.

## Fix

All output hash values now pass through recursive canonicalization:
- list arrays preserve order;
- associative arrays sort keys using `ksort(..., SORT_STRING)`;
- nested values are canonicalized recursively.

This preserves meaningful choice/recommendation sequence while making JSON object hashing deterministic across a MySQL round-trip.

Finalization-failure audit context now also records expected and actual output hashes for diagnosis.

After applying this patch, Historical Choice Optimization must be Re-processed once so the stored dataset hash is generated with the recursive canonical algorithm.
