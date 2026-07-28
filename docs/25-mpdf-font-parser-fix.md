# mPDF Bengali font parser fix

Cadre Master exposed an mPDF `TTFontFile` undefined-array-key error while compiling Windows Nirmala UI's OpenType data. The report now defaults to mPDF's bundled FreeSerif family and enables only complex-script OpenType Layout (`useOTL = 0x80`).

Nirmala UI is no longer automatically selected. A custom Unicode Bengali TTF can still be configured, provided it contains GSUB, GPOS and Bengali script tables and is not a Nirmala font.
