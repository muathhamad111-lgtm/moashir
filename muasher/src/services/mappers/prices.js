import { toPathPricing, toPriceQuote } from './paths'

function toPerson(raw) {
  return raw ? { id: raw.id ?? null, name: raw.name ?? null } : null
}

export function toPriceVersion(raw) {
  if (!raw) return null
  return {
    id: raw.id ?? null,
    priceBeforeVat: raw.priceBeforeVat ?? null,
    currency: raw.currency ?? 'SAR',
    availability: raw.availability ?? null,
    effectiveFrom: raw.effectiveFrom ?? null,
    effectiveOn: raw.effectiveOn ?? null,
    isScheduled: raw.isScheduled === true,
    setBy: toPerson(raw.setBy),
    createdAt: raw.createdAt ?? null,
    quote: toPriceQuote(raw.quote),
  }
}

export function toPriceRow(raw = {}) {
  return {
    key: raw.key ?? '',
    pricing: toPathPricing(raw.pricing),
    inForce: toPriceVersion(raw.inForce),
    scheduled: toPriceVersion(raw.scheduled),
    historyTotal: Number(raw.historyTotal ?? 0),
  }
}

export function toPricesSettings(raw = {}) {
  return {
    currency: raw.currency ?? 'SAR',
    vatRate: raw.vatRate ?? null,
    rows: (raw.rows ?? []).map(toPriceRow),
    limits: {
      priceMax: raw.limits?.priceMax ?? null,
      effectiveFromMin: raw.limits?.effectiveFromMin ?? '',
      effectiveFromMax: raw.limits?.effectiveFromMax ?? '',
    },
  }
}

function toBefore(raw) {
  return raw
    ? { priceBeforeVat: raw.priceBeforeVat ?? null, availability: raw.availability ?? null }
    : null
}

export function toPriceHistoryItem(raw = {}) {
  return {
    ...toPriceVersion(raw),
    state: raw.state ?? null,
    cancelledAt: raw.cancelledAt ?? null,
    cancelledBy: toPerson(raw.cancelledBy),
    before: toBefore(raw.before),
  }
}
