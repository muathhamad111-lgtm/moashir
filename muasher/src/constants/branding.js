export const BRAND_THEME_COLOR = '#0a2325'

export const DEFAULT_ACCENT_TOKEN = 'brand_default'

export const ACCENT_TOKENS = [
  'brand_default',
  'emerald',
  'teal',
  'cyan',
  'sky',
  'lavender',
  'amber',
  'coral',
  'lime',
]

export const CONTRAST_SURFACES = {
  onAccent: '#0a2325',
  darkContent: '#0a2325',
  lightContent: '#f5f8f8',
}

export const LOGO_RULES = {
  maxBytes: 1048576,
  maxMegabytes: 1,
  mimeTypes: ['image/png'],
  accept: '.png,image/png',
}

export const SIDEBAR_LOGO_SURFACE = 'dark'
export const FAVICON_SURFACE = 'favicon'
export const SITE_LOGO_SURFACE = 'site'

export const LOGO_SURFACES = [SIDEBAR_LOGO_SURFACE, FAVICON_SURFACE, SITE_LOGO_SURFACE]

export const LOGO_VARIANT_BY_SURFACE = {
  [SIDEBAR_LOGO_SURFACE]: 'light',
  [FAVICON_SURFACE]: 'favicon',
  [SITE_LOGO_SURFACE]: 'site',
}

export const LOGO_DIMENSIONS = Object.freeze({
  [SIDEBAR_LOGO_SURFACE]: Object.freeze({ width: 432, height: 144 }),
  [FAVICON_SURFACE]: Object.freeze({ width: 512, height: 512 }),
  [SITE_LOGO_SURFACE]: Object.freeze({ width: 480, height: 160 }),
})

export function isKnownAccent(token) {
  return ACCENT_TOKENS.includes(token)
}
