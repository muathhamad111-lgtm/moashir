import metrics from '@/styles/tokens/dropdown.module.scss'

export const DROPDOWN_METRICS = Object.freeze({
  gap: metrics.gap,
  margin: metrics.margin,
  maxBlock: metrics.maxBlock,
  flipMin: metrics.flipMin,
})

export const DROPDOWN_PLACEMENTS = Object.freeze({
  field: 'field',
  filter: 'filter',
  menu: 'menu',
  panel: 'panel',
})

export const END_ALIGNED_PLACEMENTS = Object.freeze([
  DROPDOWN_PLACEMENTS.menu,
  DROPDOWN_PLACEMENTS.panel,
])

export const SELECT_VARIANTS = Object.freeze({
  field: 'field',
  filter: 'filter',
})

export const MENU_TRIGGERS = Object.freeze({
  icon: 'icon',
  avatar: 'avatar',
})

export const POPUP_SIDES = Object.freeze({
  below: 'below',
  above: 'above',
})

export const TYPEAHEAD_RESET_MS = 500

export const LISTBOX_PAGE_STEP = 10

export const REMOTE_SEARCH_DELAY_MS = 250
