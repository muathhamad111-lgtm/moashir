import { brandingDefaultsFixture, brandingPaletteFixture } from '../fixtures/branding'
import { registerUpload } from './content/publicFiles'

const theme = { ...brandingDefaultsFixture, updatedAt: null }

export const BRANDING_FILE_PURPOSE = 'branding'

export function getBrandingTheme() {
  return theme
}

export function paletteEntry(token) {
  return brandingPaletteFixture.find((entry) => entry.token === token) ?? null
}

export function isPaletteToken(token) {
  return paletteEntry(token) !== null
}

export function updateBrandingAccent(token) {
  theme.accentToken = token
  theme.updatedAt = new Date().toISOString()
  return theme
}

const LOGO_KEYS = Object.freeze({
  light: 'logoLight',
  dark: 'logoDark',
  favicon: 'favicon',
  site: 'logoSite',
})

function logoKey(variant) {
  return LOGO_KEYS[variant]
}

export function isLogoVariant(variant) {
  return Object.hasOwn(LOGO_KEYS, variant)
}

export function storeBrandingLogo(variant, file) {
  const upload = registerUpload(file, BRANDING_FILE_PURPOSE)
  theme[logoKey(variant)] = { fileId: upload.fileId, url: upload.url }
  theme.updatedAt = new Date().toISOString()
  return upload
}

export function resetBrandingTheme() {
  Object.assign(theme, brandingDefaultsFixture, { updatedAt: new Date().toISOString() })
  return theme
}
