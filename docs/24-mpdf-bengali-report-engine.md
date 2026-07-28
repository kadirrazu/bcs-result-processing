# mPDF Bengali Report Engine

Master-data PDF exports now use `mpdf/mpdf` instead of Dompdf. Bengali needs OpenType GSUB/GPOS shaping, so the report registers a Unicode Bengali font with `useOTL => 0xFF`.

## Layout

- A4 paper
- Cadre master: landscape; subject masters: portrait
- Left/right margin: 12.7 mm (0.5 inch)
- Repeating title and timestamp on every page
- Reliable header-to-table separation through mPDF top margin
- Repeating light footer timestamp and page number
- 13pt English and Bengali table text
- Content-aware column proportions

## Font validation

The resolver accepts only fonts containing GSUB, GPOS and Bengali `bng2` or `beng` script tags. This prevents legacy ANSI Nikosh files from silently producing unreadable Unicode Bengali.
