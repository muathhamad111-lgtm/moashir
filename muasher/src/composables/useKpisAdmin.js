import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { KPI_CATEGORIES, KPI_STATUSES } from '@/constants/kpiCategories'

export const KPI_COLOR_TOKENS = ['primary', 'success', 'info', 'warning', 'danger']

export const KPI_ICON_OPTIONS = [
  'fa-solid fa-chart-line',
  'fa-solid fa-chart-column',
  'fa-solid fa-chart-pie',
  'fa-solid fa-chart-simple',
  'fa-solid fa-people-group',
  'fa-solid fa-hand-holding-heart',
  'fa-solid fa-hands-holding-circle',
  'fa-solid fa-award',
  'fa-solid fa-trophy',
  'fa-solid fa-certificate',
  'fa-solid fa-bullseye',
  'fa-solid fa-lightbulb',
  'fa-solid fa-rocket',
  'fa-solid fa-handshake',
  'fa-solid fa-landmark',
  'fa-solid fa-building',
  'fa-solid fa-user-tie',
  'fa-solid fa-users',
  'fa-solid fa-briefcase',
  'fa-solid fa-graduation-cap',
  'fa-solid fa-heart-pulse',
  'fa-solid fa-face-smile',
  'fa-solid fa-coins',
  'fa-solid fa-sack-dollar',
  'fa-solid fa-scale-balanced',
  'fa-solid fa-shield-halved',
  'fa-solid fa-brain',
  'fa-solid fa-map',
  'fa-solid fa-microchip',
  'fa-solid fa-diagram-project',
  'fa-solid fa-book-open',
  'fa-solid fa-sitemap',
  'fa-solid fa-hourglass-half',
  'fa-solid fa-id-badge',
  'fa-solid fa-calendar-check',
]

function resolve(content, locale) {
  if (content === null || content === undefined) return ''
  if (typeof content === 'string') return content
  return content[locale] || content.ar || content.en || ''
}

export function useKpisAdmin() {
  const { t, locale } = useI18n()

  function localized(content) {
    return resolve(content, locale.value)
  }

  const colorOptions = computed(() =>
    KPI_COLOR_TOKENS.map((token) => ({
      value: token,
      label: t(`kpis.admin.colors.${token}`),
    })),
  )

  const iconOptions = computed(() =>
    KPI_ICON_OPTIONS.map((icon) => ({ value: icon, label: icon })),
  )

  const publishedFilterOptions = computed(() => [
    { value: '', label: t('kpis.admin.filters.all') },
    { value: 'true', label: t('status.published') },
    { value: 'false', label: t('status.unpublished') },
  ])

  const categoryOptions = computed(() =>
    KPI_CATEGORIES.map((category) => ({
      value: category.key,
      label: t(`kpis.categories.${category.key}`),
    })),
  )

  const statusOptions = computed(() =>
    KPI_STATUSES.map((status) => ({
      value: status.key,
      label: t(`kpis.statuses.${status.key}`),
    })),
  )

  function colorLabel(token) {
    return KPI_COLOR_TOKENS.includes(token) ? t(`kpis.admin.colors.${token}`) : ''
  }

  function categoryLabel(key) {
    return KPI_CATEGORIES.some((category) => category.key === key)
      ? t(`kpis.categories.${key}`)
      : ''
  }

  function statusVariant(key) {
    return KPI_STATUSES.find((status) => status.key === key)?.variant ?? 'muted'
  }

  return {
    localized,
    colorOptions,
    iconOptions,
    publishedFilterOptions,
    categoryOptions,
    statusOptions,
    colorLabel,
    categoryLabel,
    statusVariant,
  }
}
