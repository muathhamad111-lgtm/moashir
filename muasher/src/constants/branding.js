export const BRAND_THEME_COLOR = '#0a2325'

export const DEFAULT_BRAND_COLORS = Object.freeze({ primary: '#98d8d3', secondary: '#0a2325' })

export const BRAND_SURFACES = Object.freeze({
  paper: '#ffffff',
  ink: '#100f10',
  content: '#f8f8f8',
})

export const BRAND_SHADE_STEP = 0.05

export const BRAND_COLOR_ROLES = Object.freeze(['primary', 'secondary'])

// The colours derived from the two base colours, in the order the screen lists them. The
// client may override each one; the server keys are the snake_case of these names.
export const DERIVED_BRAND_COLORS = Object.freeze([
  'onPrimary',
  'primaryText',
  'primaryHover',
  'primarySoft',
  'onSecondary',
])

// Same shares as config('moashir.branding'): hover = primary mixed with ink, soft = primary on paper.
export const BRAND_HOVER_SHARE = 0.88
export const BRAND_SOFT_SHARE = 0.1

// The sidebar gradient, as config('moashir.branding.rail_*'): the two colours blend in OKLab over
// `stops` + 1 stops; each derived share is a whole percent stepped until its text reads at AA.
export const BRAND_RAIL = Object.freeze({
  stops: 8,
  autoBottomShare: 0.26,
  glowMaxPercent: 40,
  glowStepPercent: 2,
  mutedMaxPercent: 28,
  mutedStepPercent: 2,
  accentStepPercent: 5,
})

// The two sidebar colours the client may choose; null follows the brand colours.
export const BRAND_RAIL_ROLES = Object.freeze(['top', 'bottom'])

export const BRAND_PREVIEW_SURFACES = Object.freeze(['backOffice', 'portal'])

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
