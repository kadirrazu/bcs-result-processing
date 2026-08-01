# P3 — Preliminary Reconciliation and Audited Editing

P3 adds Present/Absent/Cancelled reconciliation and manual correction of approved preliminary facts.

## Reconciliation definitions

- Present with mark: active registration + preliminary result candidate_status=active + mark present.
- Present with status text: present with mark and raw candidate_status text is preserved.
- Cancelled with reason: preliminary result candidate_status=cancelled with source status text.
- Cancelled without reason: preliminary result candidate_status=cancelled and no source status text; this is an action-report item.
- Absent: active registration with no preliminary_results row.

Counts are also grouped by cadre category: 1=GG, 2=TT, 3=GT.

## Manual edits

Mark and source candidate_status text can be edited at any time by an authorized operator. A reason is mandatory. Every edit:

1. captures the complete before snapshot;
2. applies the locked row interpretation rule;
3. captures the after snapshot;
4. writes an immutable `preliminary_processing_audits` row linked to registration/result;
5. writes the same context to the daily `preliminary` log channel;
6. stores last editor/time/reason on `preliminary_results`;
7. invalidates reconciliation and finalization snapshots so downstream processing cannot silently use stale data.

An existing cut-off mark is intentionally preserved; P4 will require re-finalization after any edit.
