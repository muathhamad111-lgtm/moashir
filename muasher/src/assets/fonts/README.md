# Approved web fonts (self-hosted, no CDN)

Operator decisions: Q-30 (2026-10-30) — IBM Plex Sans Arabic for Arabic body text, Montserrat for English text and
numbers; 2026-11-03 (Task-40) — a FREE open-licence Arabic display font closest to the identity's Iwan Zaza replaces
Tajawal for headings (Iwan Zaza itself is commercial and the guide used its personal-use build). Lama Sans and Tajawal
are withdrawn. 2026-11-16 (dashboard redesign): one Arabic family for the whole platform — IBM Plex Sans Arabic for body
text AND headings; Montserrat stays for Latin text and digits. Alexandria, Noto Kufi Arabic and Cairo were removed.
2026-11-24: Hacen Maghreb becomes the platform's Arabic font (body and headings); IBM Plex Sans Arabic stays only as
the fallback in the stack; Montserrat stays for Latin text and digits.

| File                                                  | Family               | Weights                                 | Subset (unicode-range) |
| ----------------------------------------------------- | -------------------- | --------------------------------------- | ---------------------- |
| `montserrat-latin-variable.woff2`                     | Montserrat           | 100-900 (variable)                      | Latin + digits         |
| `ibm-plex-sans-arabic-arabic-{400,500,600,700}.woff2` | IBM Plex Sans Arabic | 400, 500, 600, 700 (700 serves 700-900) | Arabic                 |
| `hacen-maghreb-{light,regular,bold}.woff`             | Hacen Maghreb        | Lt 100-400, Bd 500-700, plain 800-900   | Arabic                 |

Montserrat and IBM Plex Sans Arabic are licensed under the SIL Open Font License 1.1 (licence texts in this folder:
`OFL-*.txt`). Files are the Google Fonts builds as packaged by Fontsource.

Hacen Maghreb: HacenType commercial licence held by the platform owner; files not redistributed. They are the official
HacenType cuts (Lt, Regular, Bd) converted to WOFF without changing any glyph. They are mapped by how heavy they draw,
not by their names: Lt matches Montserrat 400, Bd is a medium-bold, and the plain cut is the heaviest, so it serves
display weights only. The files are listed in `muasher/.gitignore`, so they never reach the public repository. The
deploy kit builds the SPA from the working tree, so the servers still get them, and `deploy.sh` sets
`MOASHIR_REQUIRE_LICENSED_FONTS=true` so a deploy build fails without them. A build without the files (CI from the
public repository) succeeds without the Hacen Maghreb faces and falls back to IBM Plex Sans Arabic:
`vite-plugins/licensedFonts.js` includes `src/styles/base/hacen-maghreb.css` only when all three files are present.

The Montserrat and IBM Plex Sans Arabic `@font-face` rules live in `src/styles/base/_fonts.scss`; the Hacen Maghreb ones
in `src/styles/base/hacen-maghreb.css`. Montserrat is limited to the Latin range and listed first in every stack, so
Latin letters and Western digits render in Montserrat inside Arabic text, while Arabic letters fall through to Hacen
Maghreb, or to IBM Plex Sans Arabic without it. Stacks: `tokens/_typography.scss`.
