import { kpiCategoriesFixture, kpiTypesFixture, kpisFixture } from '../../fixtures/kpis'

const ID_START = Object.freeze({ kpi: 900, category: 100, type: 100 })

export const kpiState = {
  kpis: [],
  archived: [],
  categories: [],
  types: [],
  sequence: { ...ID_START },
}

function clone(value) {
  return JSON.parse(JSON.stringify(value))
}

export function resetKpiState() {
  kpiState.kpis = clone(kpisFixture)
  kpiState.archived = []
  kpiState.categories = clone(kpiCategoriesFixture)
  kpiState.types = clone(kpiTypesFixture)
  kpiState.sequence = { ...ID_START }
}

export function nextKpiId(kind) {
  kpiState.sequence[kind] += 1
  return kpiState.sequence[kind]
}

export function liveKpis() {
  return [...kpiState.kpis].sort((a, b) => a.order - b.order || a.id - b.id)
}

export function findKpi(kpiId) {
  return kpiState.kpis.find((kpi) => kpi.id === Number(kpiId)) ?? null
}

export function findBySlug(slug) {
  return kpiState.kpis.find((kpi) => kpi.slug === slug) ?? null
}

export function catalogOf(kind) {
  return kind === 'categories' ? kpiState.categories : kpiState.types
}

export function resequenceKpis() {
  liveKpis().forEach((kpi, index) => {
    kpi.order = index + 1
  })
}

export function todayKey() {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Riyadh' }).format(new Date())
}

resetKpiState()
