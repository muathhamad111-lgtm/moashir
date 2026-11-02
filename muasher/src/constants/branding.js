export const DEVELOPED_BY = {
  labelKey: 'footer.developedBy',
  url: 'https://tts.sa',
}

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
  onAccent: '#022426',
  darkContent: '#022426',
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
