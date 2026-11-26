import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'
import { useBoardFacts } from './useBoardFacts'

// Status colours: a fixed slot per state, so a state keeps its colour whatever else is counted.
const STATE_COLORS = Object.freeze([
  'var(--chart-ordinal-1)',
  'var(--chart-ordinal-2)',
  'var(--chart-ordinal-3)',
  'var(--chart-ordinal-4)',
  'var(--chart-series-3)',
  'var(--chart-muted)',
])
const CATEGORY_COLORS = Object.freeze([
  'var(--chart-series-1)',
  'var(--chart-series-2)',
  'var(--chart-series-3)',
])

// The proposed overview's breakdowns, shaped for the chart components.
export function useBoardBreakdowns(overview) {
  const { t, locale } = useI18n()
  const data = computed(() => overview.value?.breakdowns ?? {})

  function segments(key, labelPrefix, colors = STATE_COLORS) {
    return (data.value[key] ?? []).map((row, index) => ({
      key: row.key,
      label: t(`${labelPrefix}.${row.key}`),
      value: Number(row.count) || 0,
      color: colors[index % colors.length],
    }))
  }

  const requestStates = computed(() =>
    segments('requestsByStatus', 'dashboard.board.requestStates'),
  )
  const paymentStates = computed(() =>
    segments('paymentsByStatus', 'dashboard.board.paymentStates'),
  )
  const types = computed(() =>
    segments('entitiesByType', 'entities.enums.entityType', CATEGORY_COLORS),
  )
  const sizes = computed(() => segments('entitiesBySize', 'entities.enums.orgSize'))

  // Named rows (assessors, regions, indicators): the localized name and the counted field.
  function named(key, field) {
    return computed(() =>
      (data.value[key] ?? []).map((row) => ({
        key: row.key,
        label: localizedText(row.name, locale.value),
        value: Number(row[field]) || 0,
        target: row.target,
      })),
    )
  }

  const storage = computed(() => data.value.storage ?? null)

  return {
    requestStates,
    paymentStates,
    types,
    sizes,
    workload: named('assessorWorkload', 'open'),
    regions: named('entitiesByRegion', 'count'),
    targets: named('kpiTargets', 'value'),
    storage,
    ...useBoardFacts(data),
  }
}
