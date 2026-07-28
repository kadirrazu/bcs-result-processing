# Master Data PDF Bangla Typography

## Purpose

Ensure master-data PDF exports render Bangla correctly and remain practical for hard-copy verification.

## Rules

- Bangla text uses the locally available Nikosh TrueType font at 13pt.
- English and numeric table content uses 13pt.
- A4 page margin is 0.5 inch on all four sides; the repeating header/footer are positioned inside those margin areas.
- The master-table name and generation timestamp repeat on every page.
- A light timestamp footer repeats on every page.
- Table headers repeat after page breaks.
- Column widths are proportional to content importance instead of equal fixed widths.
- Cadre exports remain landscape; subject exports remain portrait.

## Font resolution order

1. `MASTER_DATA_PDF_NIKOSH_PATH` from `.env`
2. `storage/app/fonts/Nikosh.ttf`
3. `public/fonts/Nikosh.ttf`
4. `C:/Windows/Fonts/Nikosh.ttf`

The application never stores or distributes the font binary in the update package.
