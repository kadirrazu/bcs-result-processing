# Allocation A6 — Final Allocation Summary

## Purpose
A6 exposes a read-only Allocation Summary bound to the current finalized A5 / current A4 result. It does not recalculate or mutate Allocation.

## Ordering and identity
Rows follow Circular category, `cadre_serial`, `sub_serial`, then effective code order. English Circular snapshots are used for Cadre Name and Post Name.

## Seat interpretation
- **Overall:** sanctioned posts, final allocated, final vacant.
- **Merit Pool:** original MQ posts, quota posts converted into NM/merit capacity, final merit capacity, final MQ/merit allocations, unused merit capacity.
- **CFF / EM / PHC:** original quota post, final allocation under that quota, quota posts converted to NM, and remaining unoccupied/unconverted quota posts.
- **Phase-2 Movement:** final NM allocations, shifted allocations and same-cadre quota-to-merit normalizations.

For CFF/EM/PHC: `Rest = Post - Allocated - NM Converted`.
For Merit Pool: `Rest = Capacity - Allocated`.

## Publishing
The summary is visible in A6 and can be queued through the shared Reporting Export Run infrastructure as XLSX or PDF. Export filenames retain the mandatory `Ymd-His` generation timestamp suffix.
