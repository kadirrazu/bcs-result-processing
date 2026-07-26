# Requirements Baseline

## 1. Status and Authority

This document records the currently approved functional and non-functional requirements for the BCS Result Processing System. Existing agreed business logic remains active unless an explicit approved change supersedes it.

## 2. System Architecture Requirements

- Laravel 13 with PHP 8.3 or later and MySQL 8.
- Blade, Vite, and Tabler for the administrative interface.
- Thin controllers.
- Form Requests for request validation and authorization entry points.
- Application Actions for use-case orchestration.
- Application Services for reusable multi-step application logic.
- Domain rules isolated from transport and presentation concerns.
- Repositories only where they provide meaningful query or persistence abstraction.
- Transactions for consequential multi-record changes.
- Processing runs for long-running or restartable operations.
- Tests for critical business rules and regression-sensitive processing.

## 3. Locked Processing Pipeline

```text
Import
→ Validation
→ Preliminary processing where applicable
→ Written tabulation
→ Written result
→ Final tabulation
→ Merit generation
→ Choice optimization
→ Allocation
→ Approval and publication
```

Each stage must expose its prerequisites, status, blocking issues, processing run, summary, and audit information.

## 4. Preliminary Examination Requirements

### 4.1 Marks Import

- Preliminary marks are imported for all applicable candidates of a selected BCS.
- Each imported row must be traceable to an import batch and source row.
- Valid numeric marks, absence, missing values, duplicates, and invalid values must be distinguished.
- Blocking validation errors must prevent result finalization.

### 4.2 Distribution and Cumulative Report

After a valid import, the system must produce a report containing at least:

- Mark value.
- Number of candidates who received exactly that mark.
- Cumulative number of candidates who received that mark or a higher mark.
- Total candidates.
- Valid marks count.
- Absent count.
- Invalid or unresolved count.
- Import batch and report-generation reference.

The report is ordered from the highest mark to the lowest mark.

For a mark `M`:

```text
cumulative_count(M) = count of valid candidates where mark >= M
```

### 4.3 Cut-off Management

- A cut-off mark belongs to a specific BCS/examination.
- The operator can save a draft cut-off.
- The system shows an impact preview before approval.
- The preview includes candidates above, below, and exactly at the proposed cut-off.
- Cut-off approval requires authorization.
- The approved cut-off and its decision history remain stored and auditable.
- A cut-off change creates a new decision/version; it must not silently overwrite history.

### 4.4 Preliminary Result Rule

For candidates with a valid numeric mark:

```text
mark >= approved cut-off → PASS
mark < approved cut-off  → FAIL
```

Additional states include:

- `PENDING`
- `PASS`
- `FAIL`
- `ABSENT`
- `INVALID`

Missing or invalid required data blocks final processing until resolved or formally handled under an approved rule.

### 4.5 Preliminary Result List

- The official passed-candidate list contains candidates whose processed preliminary result is `PASS`.
- Default ordering is ascending registration/roll order.
- The result supports preview, print, PDF, and approved data export.
- Processing completion, approval, and publication remain separate states.

## 5. Written Examination Requirements

### 5.1 Written Pass Rules

- Overall written pass threshold is 50% of the applicable written total unless configured otherwise for a BCS.
- Absence in a mandatory paper causes written failure.
- A paper score below 30% is treated according to the approved paper-crash rule, including setting that paper contribution to zero where applicable.
- Papers `008` and `009` are evaluated together as a combined 100-mark examination with a 30% combined threshold, subject to the BCS-specific metadata.
- Every required subject value must be present as a valid numeric value or an approved absence marker.
- Written rules must be metadata-driven where they vary by BCS, candidate category, or subject combination.

### 5.2 Category-Wise Written Results

- Candidates satisfying the approved written business rules receive a written result status.
- Written-passed candidate lists can be generated and printed by applicable category.
- Supported views include general, technical, both-category, technical subject/cadre eligibility, and all written-passed candidates where applicable.
- Default official ordering is category followed by ascending registration/roll unless an approved report specification states otherwise.
- Candidate identity is not duplicated merely to place a candidate in multiple report views.

## 6. Viva Requirements

- Viva pass threshold is 50% of the applicable viva total unless configured otherwise.
- A candidate must satisfy the applicable written and viva pass requirements to enter final merit processing.
- Viva marks and board/date metadata remain traceable to their source and changes.

## 7. Candidate and Choice Requirements

- Registration/roll is an 8-digit numeric identifier where applicable and is not corrected through ordinary processing.
- `user_id` is a 10-character alphanumeric candidate identity where present.
- Candidate categories are `GG`, `TT`, and `GT`; legacy `T` is normalized to `TT` during import.
- A choice list contains up to 20 choices and normally at least 3 where the governing rules require it.
- Invalid and duplicate choices are detected, reported, and handled under the approved cleanup policy.
- Technical choices must satisfy subject/cadre eligibility metadata.
- `GT` candidates participate in both applicable general and technical flows without duplicating the candidate record.

## 8. Merit Requirements

A candidate is eligible for merit generation only after satisfying all applicable pass requirements.

### 8.1 General Merit

- General merit is a unique ordered position among applicable candidates.

### 8.2 Technical Merit

- Technical merit is generated separately for each applicable technical cadre/discipline.
- Per-cadre ranks may be stored as a JSON mapping such as `{"MEDI": 5, "DENT": 2}`.

### 8.3 Tie-Breakers

The strict order is:

1. Higher grand total.
2. Higher written total.
3. Higher preliminary total.
4. Older candidate.

The final ordering must be deterministic and produce unique merit positions.

## 9. Quota and Allocation Requirements

- Allocation categories include `MQ`, `CFF`, `EM`, and `PHC`.
- Allocation attempts merit quota first, followed by applicable quota priority `CFF → EM → PHC`.
- A candidate uses no more than one quota category for a final allocation.
- When a higher choice is obtainable through general merit, quota should not be consumed unnecessarily.
- Post counts are stored as approved numbers rather than recalculated solely from percentages.
- The existing verified allocation engine remains authoritative and is integrated through an adapter.
- Allocation processing must support upgrades, release of previous posts, post-balance updates, allocation status, and remaining higher choices.

## 10. Processing and Audit Requirements

Every consequential processing run must record:

- Run type.
- BCS/examination.
- Input/import references.
- Configuration/rule-set version.
- Initiating user.
- Start and finish times.
- Current status and checkpoints.
- Counts of processed, passed, failed, skipped, invalid, and errored records as applicable.
- Error summary and row-level issue references.
- Approval and publication state where relevant.

Processing must be:

- Idempotent where feasible.
- Safe to retry.
- Chunked for large datasets.
- Transactional at appropriate boundaries.
- Protected from accidental duplicate execution.
- Observable through logs and operator-facing progress.

## 11. Security Requirements

- Routes alone are not sufficient authorization.
- Sensitive use cases require policies, gates, or explicit permission checks.
- Inactive users cannot authenticate or continue privileged processing.
- Successful login metadata is recorded.
- Cut-off approval, result approval, publication, reprocessing, and allocation execution require explicit authorization.
- Secrets remain outside source control; `.env` must not be shared or committed.

## 12. Reporting Requirements

The system supports:

- On-screen preview.
- Printer-friendly rendering.
- PDF output.
- Excel/CSV output where appropriate.
- Report parameters and generation metadata.
- Versioned official result outputs.
- Internal analytical reports separated from official publications.

## 13. Non-Functional Requirements

- Maintainability through consistent modules and dependency boundaries.
- Performance suitable for thousands to hundreds of thousands of candidate rows.
- Database indexing based on actual query and processing patterns.
- Reliable backup and recovery procedures.
- Clear operational error messages without exposing sensitive implementation details.
- Reproducible builds using committed dependency lock files.
- Documentation, tests, code, and database schema kept synchronized.

## 14. Change Control

A new business rule is classified as one of:

- Architecture-level decision.
- Database/workflow-impacting requirement.
- Module-level business rule.

Architecture and database/workflow-impacting changes must be assessed and documented before implementation. Module-level details may be elaborated during module design, but known rules should be captured in the baseline backlog as early as possible.
