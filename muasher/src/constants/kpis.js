export const KPI_STATUSES = Object.freeze([
  { key: 'excellent', variant: 'success' },
  { key: 'good', variant: 'info' },
  { key: 'needs-improvement', variant: 'warning' },
  { key: 'behind-target', variant: 'danger' },
])

export const KPI_STATUS_KEYS = Object.freeze(KPI_STATUSES.map((status) => status.key))

export const KPI_STATUS_VARIANTS = Object.freeze(
  Object.fromEntries(KPI_STATUSES.map((status) => [status.key, status.variant])),
)

export const KPI_COLOR_TOKENS = Object.freeze(['primary', 'success', 'info', 'warning', 'danger'])

export const DEFAULT_KPI_COLOR = 'primary'

export const KPI_PUBLIC_SORTS = Object.freeze([
  { value: 'order', labelKey: 'order' },
  { value: 'name', labelKey: 'name' },
  { value: '-created_at', labelKey: 'newest' },
  { value: '-value', labelKey: 'valueDesc' },
  { value: 'value', labelKey: 'valueAsc' },
  { value: '-last_update', labelKey: 'lastUpdate' },
])

export const KPI_ADMIN_SORTS = Object.freeze([
  { value: 'order', labelKey: 'order' },
  { value: 'name', labelKey: 'nameAsc' },
  { value: '-name', labelKey: 'nameDesc' },
  { value: '-updated_at', labelKey: 'recentlyEdited' },
  { value: '-created_at', labelKey: 'newest' },
])

export const KPI_DEFAULT_SORT = 'order'

export const KPI_PAGE_SIZE = Object.freeze({ public: 12, admin: 15, all: 100 })

export const KPI_SEARCH_MIN = 2

export const KPI_SEARCH_DEBOUNCE_MS = 300

export const KPI_YEAR_RANGE = Object.freeze({ min: 2000, max: 2100 })

export const KPI_YEAR_FILTER_SPAN = 10

export const KPI_NUMBER_PATTERN = /^-?\d{1,14}(\.\d{1,4})?$/

export const KPI_SLUG_PATTERN = /^[a-z0-9]+(-[a-z0-9]+)*$/

export const KPI_CATALOG_KEY_PATTERN = /^[a-z0-9]+([_-][a-z0-9]+)*$/

export const KPI_REORDER = Object.freeze({ UP: 'up', DOWN: 'down' })

export const HOME_STATS_MAXIMUM = 4

export const KPI_LIMITS = Object.freeze({
  name: 200,
  unit: 60,
  shortDescription: 1000,
  fullDescription: 10000,
  owner: 200,
  dataSource: 300,
  calcMethod: 2000,
  notes: 2000,
  slug: 80,
  externalLink: 300,
  catalogName: 120,
  catalogDescription: 500,
  categoryKey: 60,
  typeKey: 40,
})

export const KPI_ICON_OPTIONS = Object.freeze([
  'chart-line',
  'chart-bar',
  'pie-chart',
  'users-two',
  'heart',
  'hands-holding',
  'award',
  'trophy',
  'certificate',
  'target',
  'lightbulb',
  'lightning',
  'handshake',
  'landmark',
  'building',
  'user',
  'briefcase',
  'graduation-cap',
  'smile',
  'sar',
  'scale',
  'shield-info',
  'brain',
  'map',
  'chip',
  'diagram',
  'book-open',
  'hierarchy',
  'refresh-dots',
  'user-circle',
  'calendar',
])

export const DEFAULT_KPI_ICON = 'chart-bar'
