import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  ACTIVITY_SERIES,
  GOVERNANCE_BANDS,
  OVERVIEW_PATHS,
  PATH_SERIES,
  REVENUE_SERIES,
} from '@/constants/dashboardOverview'
import { LEVEL_BUCKETS, LEVEL_FILTER_VALUES, LEVEL_QUERY_KEY } from '@/constants/levels'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { accessFor } from '@/constants/routeAccess'
import { usePermissions } from '@/composables/usePermissions'
import { isMetalTier } from '@/utils/levelTier'
import { bucketLabel } from '@/utils/charts/bucketLabels'
import { barLabelOf } from '@/utils/dashboard/barLabels'
import { BAR_LABELS } from '@/constants/dashboardWidgets'

// Categorical slots in a fixed order: a series keeps its colour whatever else is shown.
const SERIES_COLORS = Object.freeze([
  'var(--chart-series-1)',
  'var(--chart-series-2)',
  'var(--chart-series-3)',
])
const SINGLE_COLOR = 'var(--chart-single)'
const ORDINAL_COLORS = Object.freeze([
  'var(--chart-ordinal-1)',
  'var(--chart-ordinal-2)',
  'var(--chart-ordinal-3)',
  'var(--chart-ordinal-4)',
  'var(--chart-muted)',
])
const NO_LEVEL_COLORS = Object.freeze({
  none: 'var(--chart-level-none)',
  in_progress: 'var(--chart-level-progress)',
  participation: 'var(--chart-level-participation)',
})

function snapshotData(overview, key) {
  return overview.value?.snapshot.find((widget) => widget.key === key)?.data ?? []
}

function countsByKey(items) {
  return new Map(
    (Array.isArray(items) ? items : []).map((item) => [item.key, Number(item.count) || 0]),
  )
}

export function useOverviewCharts(overview) {
  const { t, te, locale } = useI18n()
  const { canAccess } = usePermissions()
  const linksToList = computed(() => canAccess(accessFor(ROUTE_NAMES.ADMIN_ENTITIES)))
  const trends = computed(
    () => overview.value?.trends ?? { bucket: 'day', buckets: [], series: {} },
  )
  const categories = computed(() =>
    trends.value.buckets.map((day) => bucketLabel(day, trends.value.bucket, locale.value)),
  )

  const activity = computed(() =>
    ACTIVITY_SERIES.map((key, index) => ({
      key,
      label: t(`dashboard.overview.series.${key}`),
      values: trends.value.series[key] ?? [],
      color: SERIES_COLORS[index],
    })),
  )

  const revenue = computed(() => [
    {
      key: REVENUE_SERIES,
      label: t(`dashboard.overview.series.${REVENUE_SERIES}`),
      values: trends.value.series[REVENUE_SERIES] ?? [],
      color: SINGLE_COLOR,
    },
  ])

  const paths = computed(() => {
    const byPath = new Map((overview.value?.paths ?? []).map((row) => [row.path, row]))
    return {
      categories: OVERVIEW_PATHS.map((path) => t(`dashboard.overview.pathShort.${path}`)),
      series: PATH_SERIES.map((key, index) => ({
        key,
        label: t(`dashboard.overview.series.${key}`),
        values: OVERVIEW_PATHS.map((path) => byPath.get(path)?.[key] ?? null),
        color: SERIES_COLORS[index],
      })),
    }
  })

  // A level opens the entities list filtered by it, for staff who may see that list.
  function levelLink(key, tier) {
    if (!linksToList.value || !LEVEL_FILTER_VALUES.includes(key)) return {}
    return {
      to: { name: ROUTE_NAMES.ADMIN_ENTITIES, query: { [LEVEL_QUERY_KEY]: key } },
      linkLabel: t('dashboard.levels.openList', { level: t(`level.short.${tier}`) }),
    }
  }

  const levels = computed(() => {
    const counts = countsByKey(snapshotData(overview, 'entities_by_level'))
    return Object.entries(LEVEL_BUCKETS).map(([key, tier]) => ({
      key,
      label: t(`level.short.${tier}`),
      value: counts.get(key) ?? 0,
      color: isMetalTier(tier) ? `var(--tier-${tier}-graphic)` : NO_LEVEL_COLORS[key],
      ...levelLink(key, tier),
    }))
  })

  const governance = computed(() => {
    const counts = countsByKey(snapshotData(overview, 'entities_by_governance'))
    return GOVERNANCE_BANDS.map((key, index) => ({
      key,
      label: barLabelOf(BAR_LABELS.BAND, { key }, { t, te, locale: locale.value }),
      value: counts.get(key) ?? 0,
      color: ORDINAL_COLORS[index],
    }))
  })

  const decades = computed(() => {
    const items = snapshotData(overview, 'entities_by_founding_decade')
    return {
      categories: items.map((item) =>
        barLabelOf(BAR_LABELS.DECADE, item, { t, te, locale: locale.value }),
      ),
      series: [
        {
          key: 'entities',
          label: t('dashboard.overview.series.entities'),
          values: items.map((item) => Number(item.count) || 0),
          color: SINGLE_COLOR,
        },
      ],
    }
  })

  function shareRows(key, label) {
    return (Array.isArray(snapshotData(overview, key)) ? snapshotData(overview, key) : []).map(
      (item, index) => ({
        key: String(item.key ?? index),
        label: barLabelOf(label, item, { t, te, locale: locale.value }),
        value: Number(item.count) || 0,
      }),
    )
  }

  const sectors = computed(() => shareRows('entities_by_sector', BAR_LABELS.SECTOR))

  function widget(key) {
    return { key, data: snapshotData(overview, key) }
  }

  return {
    categories,
    activity,
    revenue,
    paths,
    levels,
    governance,
    decades,
    sectors,
    widget,
  }
}
