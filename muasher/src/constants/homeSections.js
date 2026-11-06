import { ROUTE_NAMES } from './routeNames'

export const HOME_ANCHORS = Object.freeze({
  hero: 'hero',
  tour: 'tour',
  paths: 'paths',
  model: 'model',
  levels: 'levels',
  journey: 'journey',
  kit: 'kit',
  start: 'start',
  leaders: 'leaders',
  contact: 'contact',
})

export const HOME_NAV_LINKS = Object.freeze([
  { id: HOME_ANCHORS.hero, labelKey: 'nav.home' },
  { id: HOME_ANCHORS.tour, labelKey: 'nav.tour' },
  { id: HOME_ANCHORS.paths, labelKey: 'nav.paths' },
  { id: HOME_ANCHORS.model, labelKey: 'nav.model' },
  { id: 'kpis', labelKey: 'nav.kpis', routeName: ROUTE_NAMES.PUBLIC_KPIS },
  { id: HOME_ANCHORS.contact, labelKey: 'nav.contact' },
])

export const HOME_SPY_ANCHORS = Object.freeze(
  HOME_NAV_LINKS.filter((link) => !link.routeName).map((link) => link.id),
)

export const TOUR_STEP_MS = 5000

export const SECTION_ALIGN = Object.freeze({ start: 'start', center: 'center' })

export const PATH_ACCENTS = Object.freeze(['calm', 'accredited', 'award'])
