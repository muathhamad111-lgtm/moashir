import { financeSettingsSeed } from './finance'

export const PRICE_KEYS = Object.freeze(['self', 'accredited', 'award', 'bag'])

const SEEDED_AT = '2026-01-01T00:00:00.000Z'

const SEEDED_PRICES = Object.freeze({
  self: '0.00',
  accredited: financeSettingsSeed.accreditedPrice,
  award: '0.00',
  bag: financeSettingsSeed.bagPrice,
})

export function priceVersionsSeed() {
  return PRICE_KEYS.map((key, index) => ({
    id: index + 1,
    key,
    priceBeforeVat: SEEDED_PRICES[key],
    availability: 'available',
    effectiveFrom: SEEDED_AT,
    previousVersionId: null,
    setBy: null,
    createdAt: SEEDED_AT,
    cancelledAt: null,
    cancelledBy: null,
  }))
}
