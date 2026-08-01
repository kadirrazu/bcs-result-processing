# Preliminary Finalization

The finalization engine applies the currently approved cut-off only to active registrations with an active preliminary candidate row and a numeric mark.

- mark >= cut-off -> PASS
- mark < cut-off -> FAIL
- normalized cancelled row -> CANCELLED and excluded from cut-off comparison
- active registration with no preliminary row -> ABSENT (derived in summary; no artificial preliminary row is created)

The final summary contains Total/GG/TT/GT counts for Passed, Failed, Cancelled and Absent.

Published PASS registration lists are always ordered by registration number ascending. Marks are never used as publication sort order.
