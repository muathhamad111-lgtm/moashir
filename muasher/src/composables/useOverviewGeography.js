import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { GEOGRAPHY_METRICS, GEOGRAPHY_STEPS } from '@/constants/dashboardOverview'
import { projectPoint } from '@/components/charts/maps/saudiRegions'
import { localizedText } from '@/utils/localizedText'
import { useOverviewFormats } from './useOverviewFormats'

const CITY_RADIUS = Object.freeze({ min: 10, max: 26 })
const TOP_REGIONS = 5

function valueOf(row, metric) {
  const value = Number(row?.[metric])
  return Number.isFinite(value) && row?.[metric] !== null ? value : 0
}

const DECIMALS = 2

function roundedFor(format, value) {
  return format === 'percent' ? Number(value.toFixed(DECIMALS)) : Math.ceil(value)
}

// Where a city sits on the map; a city added without coordinates is listed but not drawn.
function placeOf(row) {
  if (row.latitude === null || row.longitude === null) return { x: null, y: null }
  return projectPoint(Number(row.latitude), Number(row.longitude))
}

// A dot's area follows its value, so a city twice as large reads twice as heavy.
function radiusOf(value, max) {
  return CITY_RADIUS.min + (CITY_RADIUS.max - CITY_RADIUS.min) * Math.sqrt(value / max)
}

// The step (1–4) of a value against the largest one; 0 when the place has nothing.
export function stepOf(value, max) {
  if (!(value > 0) || !(max > 0)) return 0
  return Math.min(GEOGRAPHY_STEPS, Math.ceil((value / max) * GEOGRAPHY_STEPS))
}

// Rows for the overview map in the chosen measure: shaded regions, sized city dots,
// the strongest regions and the legend's value ranges.
export function useOverviewGeography(overview, metric) {
  const { locale } = useI18n()
  const formats = useOverviewFormats()
  const definition = computed(
    () => GEOGRAPHY_METRICS.find((item) => item.key === metric.value) ?? GEOGRAPHY_METRICS[0],
  )
  const display = (value) => formats[definition.value.format](value)
  const source = computed(() => overview.value?.geography ?? { regions: [], cities: [] })

  const regions = computed(() => {
    const rows = source.value.regions ?? []
    const max = Math.max(0, ...rows.map((row) => valueOf(row, metric.value)))
    return rows.map((row) => ({
      key: row.code,
      id: row.regionId,
      label: localizedText(row.name, locale.value),
      value: valueOf(row, metric.value),
      display: display(row[metric.value]),
      step: stepOf(valueOf(row, metric.value), max),
    }))
  })

  const cities = computed(() => {
    const rows = (source.value.cities ?? []).filter((row) => valueOf(row, metric.value) > 0)
    const max = Math.max(0, ...rows.map((row) => valueOf(row, metric.value)))
    const regionKeys = new Map(regions.value.map((region) => [region.id, region.key]))
    return rows.map((row) => ({
      key: `${row.regionId}-${row.code}`,
      regionKey: regionKeys.get(row.regionId) ?? '',
      label: localizedText(row.name, locale.value),
      value: valueOf(row, metric.value),
      display: display(row[metric.value]),
      radius: radiusOf(valueOf(row, metric.value), max),
      ...placeOf(row),
    }))
  })

  const ranking = computed(() =>
    regions.value
      .filter((row) => row.value > 0)
      .sort((a, b) => b.value - a.value)
      .slice(0, TOP_REGIONS),
  )

  const legend = computed(() => {
    const max = Math.max(0, ...regions.value.map((row) => row.value))
    return Array.from({ length: GEOGRAPHY_STEPS }, (_, index) => ({
      step: index + 1,
      upTo: display(roundedFor(definition.value.format, (max * (index + 1)) / GEOGRAPHY_STEPS)),
    }))
  })

  return { regions, cities, ranking, legend }
}
