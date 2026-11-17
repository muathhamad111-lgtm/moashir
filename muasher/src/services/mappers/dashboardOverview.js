function toWindow(raw) {
  return { from: raw?.from ?? '', to: raw?.to ?? '' }
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

export function toExecutiveOverview(raw = {}) {
  return {
    period: { ...toWindow(raw.period), preset: raw.period?.preset ?? 'month' },
    previousPeriod: toWindow(raw.previousPeriod),
    cards: (raw.cards ?? []).map(toCard).filter((card) => card.key),
    trends: toTrends(raw.trends),
    paths: (raw.paths ?? []).map(toPath),
    snapshot: (raw.snapshot ?? []).filter((widget) => widget?.key),
    generatedAt: raw.generatedAt ?? null,
  }
}
