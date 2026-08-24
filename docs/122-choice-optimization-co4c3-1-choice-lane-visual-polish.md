# Choice Optimization CO4C3.1 — Choice Lane Visual Polish

- Historical Choice Optimization listing now combines Input Effective Choice and Allocation-ready Choice into one compact stacked `Choice Lineage` column.
- Input is shown above Allocation-ready output.
- Each choice is rendered in one horizontal lane with three visual levels: choice serial, numeric code, and current Cadre/Sub-Cadre abbreviation.
- The lane uses local horizontal overflow so the whole table does not need to become excessively wide.
- Individual Allocation-ready Choice Detail uses the same choice-lane component for Input, Removed, and Final sequences.
- Candidate name is resolved from Registration and displayed with registration for visual confirmation.
- Cadre/Sub-Cadre abbreviations are resolved at runtime from current masters and are not redundantly stored in the CO4C3 output table.
