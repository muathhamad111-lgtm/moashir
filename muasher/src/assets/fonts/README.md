# Approved web fonts (self-hosted, no CDN)

Operator decisions: Q-30 (2026-10-30) — IBM Plex Sans Arabic for Arabic body text, Montserrat for English text and
numbers; 2026-11-03 (Task-40) — a FREE open-licence Arabic display font closest to the identity's Iwan Zaza replaces
Tajawal for headings (Iwan Zaza itself is commercial and the guide used its personal-use build). Lama Sans and Tajawal
are withdrawn. 2026-11-16 (dashboard redesign): one Arabic family for the whole platform — IBM Plex Sans Arabic for body
text AND headings; Montserrat stays for Latin text and digits. Alexandria, Noto Kufi Arabic and Cairo were removed.

| File                                                  | Family               | Weights                                 | Subset (unicode-range) |
| ----------------------------------------------------- | -------------------- | --------------------------------------- | ---------------------- |
| `montserrat-latin-variable.woff2`                     | Montserrat           | 100-900 (variable)                      | Latin + digits         |
| `ibm-plex-sans-arabic-arabic-{400,500,600,700}.woff2` | IBM Plex Sans Arabic | 400, 500, 600, 700 (700 serves 700-900) | Arabic                 |

All families are licensed under the SIL Open Font License 1.1 (licence texts in this folder: `OFL-*.txt`). Files are
the Google Fonts builds as packaged by Fontsource.

The `@font-face` rules live in `src/styles/base/_fonts.scss`. Montserrat is limited to the Latin range and listed first
in every stack, so Latin letters and Western digits render in Montserrat inside Arabic text, while Arabic letters fall
through to IBM Plex Sans Arabic. Stacks: `tokens/_typography.scss`.
