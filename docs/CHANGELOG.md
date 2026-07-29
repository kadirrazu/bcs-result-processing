# Documentation Changelog

## Unreleased

### Added
- Project vision and scope foundation.
- Requirements baseline including preliminary cut-off workflow and category-wise written result publication.
- System architecture and examination data isolation strategy.
- ADR-001: shared application code with a separate physical database per BCS examination.
- Folder structure guidance.
- Coding and commenting standards.
- Naming conventions.
- CRUD vs Action classification matrix.
- Action, Service, Domain Rule, Repository, Model, and DTO boundaries.
- Database design principles.
- Standard module development workflow.

## v1.8 - Registration Module
- Added central gender, division, district and university masters.
- Added examination-specific registration and import-batch schema.
- Added high-volume Excel import, CRUD, filters, reports and tests.
- Corrected processing navigation to Registration → Preliminary → Written → Viva → Choices.
