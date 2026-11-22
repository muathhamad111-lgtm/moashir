import { ROUTE_NAMES } from './routeNames'

export const CMS_PAGE_KEYS = Object.freeze({
  HOME: 'home',
  ACCREDITED_INTRO: 'accredited_intro',
  ADVANCED_INTRO: 'advanced_intro',
  BAG_DETAILS: 'bag_details',
  LENSES: 'lenses',
  KPIS: 'kpis',
  ACCESSIBILITY: 'accessibility',
})

export const CMS_PAGE_ORDER = Object.freeze(Object.values(CMS_PAGE_KEYS))

export const CMS_PAGE_ROUTES = Object.freeze({
  home: ROUTE_NAMES.PUBLIC_HOME,
  accredited_intro: ROUTE_NAMES.ASSESSMENT_ACCREDITED,
  advanced_intro: ROUTE_NAMES.ASSESSMENT_ADVANCED,
  bag_details: ROUTE_NAMES.BAG_DETAILS,
  lenses: ROUTE_NAMES.PUBLIC_LENSES,
  kpis: ROUTE_NAMES.PUBLIC_KPIS,
  accessibility: ROUTE_NAMES.LEGAL_ACCESSIBILITY,
})

export const CMS_SECTION_TYPES = Object.freeze([
  'hero',
  'about',
  'levels',
  'paths',
  'excellence_model',
  'how_it_works',
  'bag_promo',
  'leadership_promo',
  'lenses_brief',
  'kpi_promo',
  'partners',
  'contact',
  'footer',
  'assistant',
  'rich_text',
  'faq',
  'pricing_box',
  'cycle_box',
  'winners',
  'logos',
])

export const CMS_SECTION_VARIANTS = Object.freeze({
  how_it_works: ['journey'],
  rich_text: ['cta', 'band'],
})

export const CMS_AVAILABILITY = Object.freeze(['live', 'coming_soon'])

export const CMS_ITEM_ROLES = Object.freeze({
  hero: ['point', 'secondary-cta'],
  about: ['audience', 'goal'],
  bag_promo: ['step', 'tool'],
  rich_text: ['secondary-cta'],
})

export const CMS_LIMITS = Object.freeze({
  sectionsPerPage: 40,
  itemsPerSection: 60,
  sectionKeyMax: 40,
  backgroundMax: 30,
  ctaUrlMax: 300,
  pageTitleMax: 200,
  seoTitleMax: 120,
  seoDescriptionMax: 300,
})

export const CMS_LOCALIZED_LIMITS = Object.freeze({
  title: 300,
  subtitle: 500,
  description: 5000,
  ctaLabel: 120,
})

export const CMS_SECTION_KEY_PATTERN = /^[a-z][a-z0-9_-]*$/

export const CMS_BACKGROUND_PATTERN = /^[a-z][a-z0-9-]*$/

export const HTTPS_LINK_PATTERN = /^https:\/\/[^\s<>"]+$/

export const SAFE_LINK_PATTERNS = Object.freeze([
  /^\/(?!\/)[A-Za-z0-9\-._~/?=&%#]*$/,
  /^#[A-Za-z][A-Za-z0-9_-]*$/,
  /^mailto:[^\s@<>"]+@[^\s@<>"]+$/,
  HTTPS_LINK_PATTERN,
])

export const CMS_IMAGE_RULES = Object.freeze({
  maxBytes: 5 * 1024 * 1024,
  mimeTypes: ['image/png', 'image/jpeg'],
  accept: '.png,.jpg,.jpeg,image/png,image/jpeg',
  typeErrorKey: 'cms.image.invalidType',
  sizeErrorKey: 'cms.image.tooLarge',
})

export const CMS_LOGO_RULES = Object.freeze({
  maxBytes: 1024 * 1024,
  mimeTypes: ['image/png', 'image/jpeg', 'image/svg+xml'],
  accept: '.png,.jpg,.jpeg,.svg,image/png,image/jpeg,image/svg+xml',
  typeErrorKey: 'cms.partners.logoInvalidType',
  sizeErrorKey: 'cms.partners.logoTooLarge',
})

export const CMS_LOGO_SECTION_TYPES = Object.freeze(['partners'])

export const SITE_SETTINGS_LIMITS = Object.freeze({
  featuredMin: 3,
  featuredMax: 6,
  socialMax: 10,
  quickLinksMax: 12,
  headerLinksMax: 8,
  headerChildrenMax: 8,
})

export const SOCIAL_PLATFORM_KEYS = Object.freeze([
  'x',
  'linkedin',
  'youtube',
  'instagram',
  'facebook',
])

export const PUBLIC_PAGE_STATES = Object.freeze({
  LOADING: 'loading',
  READY: 'ready',
  UNPUBLISHED: 'unpublished',
  ERROR: 'error',
})
