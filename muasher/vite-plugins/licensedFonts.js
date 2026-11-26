import { existsSync } from 'node:fs'
import { resolve } from 'node:path'
import process from 'node:process'

// Hacen Maghreb is licensed to the platform owner, so its files are kept out of the public
// repository. Its @font-face rules ship only when the files are present; a checkout without them
// falls back to IBM Plex Sans Arabic instead of requesting fonts that do not exist. A deploy build
// sets MOASHIR_REQUIRE_LICENSED_FONTS=true and fails rather than ship without them.
export const LICENSED_FONTS_ID = 'virtual:licensed-fonts'
export const LICENSED_FONTS_STYLESHEET = 'src/styles/base/hacen-maghreb.css'
export const LICENSED_FONT_FILES = Object.freeze(
  ['light', 'regular', 'bold'].map((cut) => `src/assets/fonts/hacen-maghreb-${cut}.woff`),
)
export const ABSENT_FONTS_ID = '\0moashir-licensed-fonts-absent'
export const REQUIRE_FONTS_ENV = 'MOASHIR_REQUIRE_LICENSED_FONTS'

export function licensedFontsPresent(root) {
  return LICENSED_FONT_FILES.every((file) => existsSync(resolve(root, file)))
}

export function licensedFonts(env = process.env) {
  const state = { root: process.cwd(), present: false }

  return {
    name: 'moashir-licensed-fonts',
    enforce: 'pre',
    configResolved(config) {
      state.root = config.root
      state.present = licensedFontsPresent(config.root)
      if (!state.present && env[REQUIRE_FONTS_ENV] === 'true')
        throw new Error(`The licensed fonts are missing: ${LICENSED_FONT_FILES.join(', ')}.`)
    },
    resolveId(source) {
      if (source !== LICENSED_FONTS_ID) return null
      return state.present ? resolve(state.root, LICENSED_FONTS_STYLESHEET) : ABSENT_FONTS_ID
    },
    load(id) {
      return id === ABSENT_FONTS_ID ? '' : null
    },
  }
}
