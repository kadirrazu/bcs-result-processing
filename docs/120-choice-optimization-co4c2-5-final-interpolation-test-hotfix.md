# Choice Optimization CO4C2.5 — Final Interpolation Test Hotfix

Tests-only patch.

The prior hotfix escaped quote characters inside a double-quoted PHP string, but `$currentDistrict` was still interpolated by PHP. This patch changes the entire expected source fragment to a single-quoted PHP string so `$currentDistrict` remains literal source text.

No application/runtime code changed.
