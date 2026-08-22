# Choice Optimization — CO1 Foundation

Status: IMPLEMENTATION STARTED

CO1 establishes Choice Optimization as an optional examination-level transformation layer.

- `NO`: no optimization transformation; Allocation uses finalized Validated Choice.
- `YES`: Choice Optimization must complete before Allocation uses finalized Optimized Choice.
- Original and validated choices remain authoritative historical layers and are never overwritten.
- Later stages add Viva OMR override, consolidated effective choice, previous-BCS matching/review, trimming, finalization and allocation-ready output.
- Previous-BCS import contract columns: `bcs_number, reg, name, fname, mname, b_date, district_name, ssc_roll, ssc_year, hsc_roll, hsc_year, nid, cadre`.
- OMR registration identity must resolve against the finalized Written-qualified population before OMR choice validation. Duplicate/conflicting OMR claims require resolution and are never silently selected.
