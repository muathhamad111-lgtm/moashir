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
  mimeTypes: ['image/png', 'image/svg+xml'],
  accept: '.png,.svg,image/png,image/svg+xml',
}

export const LOGO_SURFACES = ['dark', 'light']

export const LOGO_VARIANT_BY_SURFACE = {
  dark: 'light',
  light: 'dark',
}

export function isKnownAccent(token) {
  return ACCENT_TOKENS.includes(token)
}
