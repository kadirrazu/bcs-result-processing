# Written Module W2 — Fast Import, Validation, Review and Merge

## Scope

W2 adds the operational import pipeline on top of W1:

1. XLSX/CSV fast staging with OpenSpout.
2. Strict REG + USER identity validation.
3. Preliminary PASS eligibility gate.
4. Numeric / ABS / AAA mark validation (`AAA` normalizes to `ABS`).
5. Applicable blank mandatory mark is invalid; ABS/AAA is a valid source status and is not a validation error.
6. PRS code validation against the central active post-related subject master.
7. PRS mismatch against `registrations.post_related_subject_code` is a WARNING, not an automatic cancellation.
8. Numeric marks outside the candidate's applicable track are WARNING rows.
9. Configurable high-mark review; 008+009 is reviewed as one combined 100-mark group.
10. Warning-first review UI with Valid / Warning / Invalid / Identity Conflict filters.
11. Valid and Warning rows approve/merge; invalid rows remain staging-only.
12. Actual marks and counted marks are stored separately. W2 initializes counted mark = actual mark; W3 applies paper-crash rules.

`data_source_note` never drives the Written `status` field.

The approved source is treated as the current Written appeared-candidate snapshot. Candidates absent from the approved source are removed from `written_results` and are derived as Written absent during reconciliation.
