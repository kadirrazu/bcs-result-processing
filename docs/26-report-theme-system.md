# Report Theme System

## Purpose

All PDF reports must obtain typography, page margins, table spacing, and colours from a named report theme. Report classes must not hard-code these presentation values.

## Components

- `config/reports.php` — theme definitions and default theme selection.
- `App\Reports\Themes\ReportTheme` — immutable validated theme value object.
- `App\Reports\Themes\ReportThemeManager` — resolves configured themes.
- `MasterDataPdfReport` — first consumer of the shared theme system.

## Government theme defaults

- English body: 12 pt
- Bangla body: 13 pt
- Report title: 15 pt
- Metadata: 9 pt
- Table header: 11 pt bold
- Footer: 8 pt
- Body top margin: 29 mm
- Physical left/right page margins: 12.7 mm (0.5 inch)

## Extension rule

Future candidate, merit, tabulation, allocation, and statistics reports should inject `ReportThemeManager` and pass the resolved theme into their report view. A new visual style should be introduced as another named entry under `reports.themes`, not by duplicating CSS.
