function toWindow(raw) {
  return { from: raw?.from ?? '', to: raw?.to ?? '' }
}

function toComparison(raw) {
  return { ...toWindow(raw), basis: raw?.basis ?? '' }
}

function toCard(raw = {}) {
  return {
    key: raw.key ?? '',
    value: raw.value ?? null,
    previous: raw.previous ?? null,
    format: raw.format ?? 'count',
  }
}

function toTrends(raw = {}) {
  const series = raw?.series ?? {}
  return {
    bucket: raw?.bucket ?? 'day',
    buckets: Array.isArray(raw?.buckets) ? raw.buckets : [],
    series: Object.fromEntries(
      Object.entries(series).map(([key, values]) => [key, (values ?? []).map(Number)]),
    ),
  }
}

function toPath(raw = {}) {
  const count = (value) => (value === null || value === undefined ? null : Number(value))
  return { path: raw.path ?? '', submitted: count(raw.submitted), completed: count(raw.completed) }
}

function toCoordinate(value) {
  return value === null || value === undefined ? null : Number(value)
}

// One region or city on the overview map; a city's id and coordinates are absent for a region.
function toPlace(raw = {}) {
  return {
    regionId: raw.regionId ?? null,
    cityId: raw.cityId ?? null,
    code: raw.code ?? '',
    name: raw.name ?? null,
    latitude: toCoordinate(raw.latitude),
    longitude: toCoordinate(raw.longitude),
    entities: Number(raw.entities) || 0,
    requests: Number(raw.requests) || 0,
    averagePercent: raw.averagePercent ?? null,
    collected: raw.collected ?? '0.00',
  }
}

function toGeography(raw = {}) {
  return {
    regions: (raw?.regions ?? []).map(toPlace).filter((place) => place.code),
    cities: (raw?.cities ?? []).map(toPlace).filter((place) => place.code),
  }
}

export function toExecutiveOverview(raw = {}) {
  return {
    period: { ...toWindow(raw.period), preset: raw.period?.preset ?? 'month' },
    previousPeriod: toComparison(raw.previousPeriod),
    cards: (raw.cards ?? []).map(toCard).filter((card) => card.key),
    trends: toTrends(raw.trends),
    paths: (raw.paths ?? []).map(toPath),
    snapshot: (raw.snapshot ?? []).filter((widget) => widget?.key),
    geography: toGeography(raw.geography),
    generatedAt: raw.generatedAt ?? null,
  }
}

function toCount(value) {
  return Number(value) || 0
}

export function toAssessorOverview(raw = {}) {
  return {
    period: { ...toWindow(raw.period), preset: raw.period?.preset ?? 'month' },
    previousPeriod: toComparison(raw.previousPeriod),
    cards: (raw.cards ?? []).map(toCard).filter((card) => card.key),
    trends: toTrends(raw.trends),
    paths: (raw.paths ?? []).map((row) => ({
      path: row?.path ?? '',
      completed: toCount(row?.completed),
    })),
    levels: (raw.levels ?? []).map((row) => ({ key: row?.key ?? '', count: toCount(row?.count) })),
    generatedAt: raw.generatedAt ?? null,
  }
}
