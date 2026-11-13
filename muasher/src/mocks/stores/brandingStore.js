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

function logoKey(variant) {
  return variant === 'light' ? 'logoLight' : 'logoDark'
}

export function isLogoVariant(variant) {
  return variant === 'light' || variant === 'dark'
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
