# Project Vision and Scope

## 1. Project Vision

The BCS Result Processing System is an enterprise-grade, auditable, metadata-driven platform for processing Bangladesh Civil Service examination data from import through result publication and cadre allocation.

The system must convert complex examination rules and operational workflows into repeatable, reviewable, secure, and testable processing steps while preserving the integrity of source data and every consequential decision.

## 2. Primary Objectives

The system will:

- Import candidate, examination, marks, choice, quota, post, subject, and supporting data.
- Validate imported data before it becomes eligible for processing.
- Produce preliminary mark-frequency and cumulative reports.
- Manage preliminary cut-off decisions and generate preliminary pass/fail results.
- Apply written examination business rules and produce category-wise written results.
- Perform tabulation using metadata-driven examination rules.
- Generate general and cadre-wise technical merit positions.
- Validate and optimize candidate choice lists.
- Integrate the existing verified allocation engine through a stable adapter.
- Produce operational, analytical, official, and audit reports.
- Preserve a complete history of imports, decisions, processing runs, approvals, corrections, and publications.

## 3. Guiding Principles

### 3.1 Correctness Before Convenience

A result-processing action must not complete successfully when blocking validation errors or unresolved data inconsistencies exist.

### 3.2 Auditability by Default

The system must answer:

- What data was processed?
- Which rule set and configuration were used?
- Who initiated, reviewed, or approved the action?
- When did it occur?
- What changed between versions?
- Can the exact result be reproduced?

### 3.3 Metadata-Driven Rules

Rules that vary by BCS, examination stage, subject, cadre, quota, or policy should be stored as controlled metadata where practical instead of being repeatedly hard-coded.

### 3.4 Idempotent and Restartable Processing

Long-running processing must use explicit processing runs, chunking, checkpoints, failure isolation, and safe retry behavior.

### 3.5 Separation of Calculation, Approval, and Publication

Completing a calculation does not automatically approve or publish its result. Processing state, approval state, and publication state are distinct.

### 3.6 Preserve Source Data

Raw imported values should remain traceable. Normalized or calculated values must not silently destroy the original source record.

### 3.7 Existing Allocation Logic Is Protected

The existing allocation engine is treated as a verified domain asset. It will be integrated through an adapter and regression-tested rather than redesigned without a separately approved change.

## 4. High-Level Processing Lifecycle

```text
Examination setup
→ Master data preparation
→ Candidate data import
→ Preliminary marks import
→ Preliminary distribution analysis
→ Cut-off review and approval
→ Preliminary result processing
→ Preliminary passed-candidate result
→ Written/viva/choice data import
→ Validation and correction
→ Written tabulation and rule evaluation
→ Category-wise written result
→ Final tabulation
→ General and technical merit generation
→ Choice validation and optimization
→ Allocation-engine execution
→ Allocation review and publication
→ Reports and audit archive
```

A BCS may use only the applicable stages, but bypasses must be explicit, authorized, and auditable.

## 5. In Scope

### 5.1 Administration and Security

- Authentication and secure login controls.
- Role- and permission-based authorization.
- Active/inactive user control.
- User and designation management.
- Audit trail for sensitive actions.

### 5.2 Examination Configuration

- BCS/examination definitions.
- Examination stages and statuses.
- Subjects, papers, combined papers, thresholds, and mandatory rules.
- Cadres and cadre types.
- Subject-to-cadre eligibility.
- Quota definitions and ordering.
- Post availability and category-wise post counts.

### 5.3 Import and Validation

- CSV, DBF-converted, and other approved import formats.
- Import batches and row-level validation.
- Duplicate, missing, invalid, and conflicting data detection.
- Staging, correction, revalidation, and controlled commit.

### 5.4 Preliminary Processing

- Marks import.
- Mark-wise frequency report.
- Descending cumulative count report.
- Cut-off draft, impact preview, review, and approval.
- Pass/fail processing using the approved cut-off.
- Roll/registration-wise passed-candidate result list.

### 5.5 Written and Viva Processing

- Written marks validation and tabulation.
- Mandatory absence handling.
- Paper crash and combined-paper rules.
- Overall written pass threshold.
- Viva marks and viva pass rules.
- Category-wise written result lists.

### 5.6 Merit and Allocation

- General merit.
- Cadre-wise technical merit.
- Tie-breakers and unique ordering.
- Choice cleaning and eligibility validation.
- Existing allocation-engine integration.
- Allocation status, upgrades, releases, waiting/higher choices, and post balances.

### 5.7 Reporting and Publication

- Internal analytical reports.
- Validation and exception reports.
- Processing summaries.
- Printable and exportable official result lists.
- Versioned publication records.

## 6. Out of Scope Unless Separately Approved

- Replacing the existing verified allocation algorithm.
- Public candidate self-service portals.
- Online application submission.
- Payment processing.
- Full document-management or archival-scanning systems.
- Integration with external government systems without a defined interface and approval.
- Mobile applications.

## 7. Stakeholders

- Examination administrators.
- Data-entry and import operators.
- Result-processing officers.
- Reviewers and approving authorities.
- System administrators.
- Auditors and authorized report consumers.
- Development and support teams.

## 8. Success Criteria

The project is successful when:

- Approved rules are applied consistently and verified by automated tests.
- Critical processing outputs are reproducible from recorded inputs and configuration.
- No official result can be published without the required validation and approval controls.
- Large candidate datasets can be processed safely with visible progress and recoverable failures.
- Operators can identify, correct, and reprocess invalid data without corrupting historical results.
- Existing allocation outcomes remain regression-compatible after integration.
- Documentation, code, tests, and database behavior remain synchronized.
