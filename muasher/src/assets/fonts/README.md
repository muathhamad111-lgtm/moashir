# Approved web fonts (self-hosted, no CDN)

Decision Q-30 (operator 2026-10-30): Tajawal for Arabic headings, IBM Plex Sans Arabic for Arabic
body text, Montserrat for English text and numbers. Lama Sans is withdrawn.

| File | Family | Weights | Subset (unicode-range) |
| --- | --- | --- | --- |
| `montserrat-latin-variable.woff2` | Montserrat | 100-900 (variable) | Latin + digits |
| `ibm-plex-sans-arabic-arabic-{400,500,600,700}.woff2` | IBM Plex Sans Arabic | 400, 500, 600, 700 | Arabic |
| `tajawal-arabic-{400,500,700,900}.woff2` | Tajawal | 400, 500, 700, 900 | Arabic |

All three families are licensed under the SIL Open Font License 1.1 (license texts in this
folder: `OFL-*.txt`). Files are the Google Fonts builds as packaged by Fontsource.

The `@font-face` rules live in `src/styles/base/_fonts.scss`. Montserrat is limited to the Latin
range and listed first in every stack, so Latin letters and Western digits render in Montserrat
inside Arabic text, while Arabic letters fall through to IBM Plex Sans Arabic (body) or Tajawal
(headings). Stacks: `tokens/_typography.scss`.
