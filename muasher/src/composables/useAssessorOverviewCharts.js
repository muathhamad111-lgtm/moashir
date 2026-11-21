import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ASSESSOR_OPEN_STATES, ASSESSOR_PATHS } from '@/constants/dashboardOverview'
import { bucketLabel } from '@/utils/charts/bucketLabels'

const SINGLE_COLOR = 'var(--chart-single)'
const STATE_COLORS = Object.freeze([
  'var(--chart-series-1)',
  'var(--chart-series-2)',
  'var(--chart-series-3)',
])
const BELOW_LEVELS = 'none'

function levelColor(key) {
  return key === BELOW_LEVELS ? 'var(--chart-level-none)' : `var(--tier-${key}-graphic)`
}

function levelSegments(rows, t) {
  return rows.map((row) => ({
    key: row.key,
    label:
      row.key === BELOW_LEVELS
        ? t('dashboard.assessorOverview.belowLevels')
        : t(`level.short.${row.key}`),
    value: row.count,
    color: levelColor(row.key),
  }))
}

// The assessor overview's chart data: the completed reviews over the period, the open reviews by
// state (live), the completed reviews by path, and the levels the assessor's results reached.
export function useAssessorOverviewCharts(overview, liveCards) {
  const { t, locale } = useI18n()
  const trends = computed(
    () => overview.value?.trends ?? { bucket: 'day', buckets: [], series: {} },
  )

  const categories = computed(() =>
    trends.value.buckets.map((day) => bucketLabel(day, trends.value.bucket, locale.value)),
  )
  const completed = computed(() => [
    {
      key: 'completed',
      label: t('dashboard.assessorOverview.series.completed'),
      values: trends.value.series.completed ?? [],
      color: SINGLE_COLOR,
    },
  ])

  const openStates = computed(() =>
    ASSESSOR_OPEN_STATES.map((key, index) => ({
      key,
      label: t(`dashboard.cards.${key}`),
      value: Number(liveCards.value.find((card) => card.key === key)?.value) || 0,
      color: STATE_COLORS[index],
    })),
  )

  const paths = computed(() => {
    const byPath = new Map((overview.value?.paths ?? []).map((row) => [row.path, row.completed]))
    return {
      categories: ASSESSOR_PATHS.map((path) => t(`dashboard.overview.pathShort.${path}`)),
      series: [
        {
          key: 'completed',
          label: t('dashboard.assessorOverview.series.completed'),
          values: ASSESSOR_PATHS.map((path) => byPath.get(path) ?? 0),
          color: SINGLE_COLOR,
        },
      ],
    }
  })

  const levels = computed(() => levelSegments(overview.value?.levels ?? [], t))

  return { categories, completed, openStates, paths, levels }
}
