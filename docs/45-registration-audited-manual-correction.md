# Registration Audited Manual Correction

Manual edits to an examination registration now require a reason and create an immutable candidate-level audit record.

Recorded facts: registration id, action, operator id/name, reason, changed fields, complete before/after snapshots, IP, user-agent and GMT+6-aware timestamp. The same event is written to the `registration` daily log channel.

No audit row is created when an edit form is submitted without an actual data change. Import batch auditing remains separate; this patch does not create hundreds of thousands of row-level audit entries during spreadsheet merge.
