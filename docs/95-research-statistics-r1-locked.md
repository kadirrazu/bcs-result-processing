# Research & Statistics Section Reporting — R1 Locked Contract

## Authoritative populations
- Applicant Candidates: Registration population.
- Preliminary Qualified Candidates: current finalized Preliminary PASS population.
- Written Qualified Candidates: current finalized Written population with a generated Written Qualified Track.
- Allocated / Recommended Candidates: current finalized A5 PASS allocation population after A5.5 publication control; ACTIVE only (WITHHELD/CANCELLED excluded).

Each population reports Overall, Division-wise, District-wise and Age Group-wise counts with gender breakdown.

## Age authority and groups
Age is completed age on Examination `age_calculation_date`.
Groups: Below 21; 21-23; 24-26; 27-29; 30 and above. Unknown age is shown only when DOB is unavailable. Applicable grouped tables end with a Total row.

## Recommended-only statistics
- Bachelor Subject (`b_subject` / `bachelor_subject_code`) mapped through Bachelor Subject master, gender-wise.
- General Cadre recommended: overall, division-wise, district-wise; gender breakdown.
- Technical Cadre recommended: overall, division-wise, district-wise; gender breakdown.
- General Education / Technical Education recommended: parent cadre codes 610, 620, 630, 640, 660 (including their sub-cadre effective codes); overall, division-wise, district-wise; gender breakdown.
- University / College / Institute statistics from Registration `university_code`, mapped through University master.
- Top 10 educational institutions by ACTIVE recommended candidate count, with gender breakdown. Its footer is explicitly `Total — Top 10 Institutions`, not the overall recommended total.

## Currentness
Statistics sections are independently gated. Applicant remains available from Registration. Preliminary and Written require their own current finalized authority. Recommended statistics use the same fail-closed Allocation publishing authority as A6.
