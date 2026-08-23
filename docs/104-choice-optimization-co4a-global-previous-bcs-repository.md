# Choice Optimization CO4A — Global Previous BCS Recommendation Repository

Status: foundation implemented.

## Scope

This is central reusable reference data, not examination-workspace data.

- One central repository identity per BCS number.
- Each re-upload creates the next dataset version; older versions remain preserved.
- Excel does not contain `bcs_number`; the operator enters BCS Number before upload.
- Import/staging runs on the shared `imports` queue and is monitored through JSON polling.
- CO4A stages and normalizes source data only. CO4B will add full validation, approval and effective-version authority.
- Current-examination search/matching/incorporation remains CO4C.

## Exact columns

`reg, name, fname, mname, b_date, dob, dist_name, ssc_roll, ssc_year, hsc_roll, hsc_year, nid_no, cadre`

Optional and allowed blank:

`fname, mname, dob, dist_name, nid_no`

## Date contract

- `b_date` is required primary DOB evidence and accepts DDMMYY or DDMMYYYY.
- Raw `b_date` is preserved and a normalized database date is stored.
- `dob` is optional secondary DOB evidence, commonly MM/DD/YYYY; several strict formats and a conservative parse fallback are supported.
- Raw `dob` is preserved separately.
- For DDMMYY, the two-digit year is resolved relative to the current two-digit year: values up to the current YY map to 20YY, later values map to 19YY.

## Data boundary

Global repository rows remain central. Later, confirmed historical matches will be incorporated as examination-specific derived records inside the current BCS Choice Optimization workspace.
