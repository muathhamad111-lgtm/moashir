export const BRAND_THEME_COLOR = '#0a2325'

export const DEFAULT_BRAND_COLORS = Object.freeze({ primary: '#98d8d3', secondary: '#0a2325' })

export const BRAND_SURFACES = Object.freeze({
  paper: '#ffffff',
  ink: '#100f10',
  content: '#f8f8f8',
})

export const BRAND_SHADE_STEP = 0.05

export const BRAND_COLOR_ROLES = Object.freeze(['primary', 'secondary'])

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
