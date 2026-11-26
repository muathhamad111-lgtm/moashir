import { brandingDefaultsFixture } from '../fixtures/branding'
import { registerUpload } from './content/publicFiles'

const theme = { ...brandingDefaultsFixture, colorOverrides: {}, updatedAt: null }

export const BRANDING_FILE_PURPOSE = 'branding'

export function getBrandingTheme() {
  return theme
}

export function updateBrandingColors({ primary, secondary, overrides, rail }) {
  theme.primaryColor = primary
  theme.secondaryColor = secondary
  theme.colorOverrides = { ...overrides }
  theme.sidebarTopColor = rail.top
  theme.sidebarBottomColor = rail.bottom
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
  Object.assign(theme, brandingDefaultsFixture, {
    colorOverrides: {},
    updatedAt: new Date().toISOString(),
  })
  return theme
}
