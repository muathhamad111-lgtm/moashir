import { brandingDefaultsFixture, brandingPaletteFixture } from '../fixtures/branding'

const theme = { ...brandingDefaultsFixture, updatedAt: null }
let fileSeed = 7100

export function getBrandingTheme() {
  return theme
}

export function isPaletteToken(token) {
  return brandingPaletteFixture.some((entry) => entry.token === token)
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

export function storeBrandingLogo(variant, url) {
  fileSeed += 1
  const logo = { fileId: fileSeed, url }
  theme[logoKey(variant)] = logo
  theme.updatedAt = new Date().toISOString()
  return logo
}

export function resetBrandingTheme() {
  Object.assign(theme, brandingDefaultsFixture, { updatedAt: new Date().toISOString() })
  return theme
}
