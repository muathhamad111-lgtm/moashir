import { PRICE_KEYS } from '../../fixtures/priceVersions'
import { financeState } from './financeState'
import { quoteOf } from './financeMoney'

export { PRICE_KEYS }

const UNSET = Object.freeze({ price: null, isFree: false, availability: 'paused' })

function timeOf(iso) {
  return new Date(iso).getTime()
}

function newestEffective(left, right) {
  return timeOf(right.effectiveFrom) - timeOf(left.effectiveFrom) || right.id - left.id
}

export function versionsOf(key) {
  return financeState().priceVersions.filter((version) => version.key === key)
}

function liveVersions(key) {
  return versionsOf(key).filter((version) => !version.cancelledAt)
}

export function findPriceVersion(id) {
  return financeState().priceVersions.find((version) => version.id === id) ?? null
}

export function inForceVersion(key, now = Date.now()) {
  return (
    liveVersions(key)
      .filter((version) => timeOf(version.effectiveFrom) <= now)
      .sort(newestEffective)[0] ?? null
  )
}

export function scheduledVersion(key, now = Date.now()) {
  return liveVersions(key).find((version) => timeOf(version.effectiveFrom) > now) ?? null
}

export function isScheduled(version, now = Date.now()) {
  return !version.cancelledAt && timeOf(version.effectiveFrom) > now
}

export function currentVatRate() {
  return financeState().settings.vatRate
}

export function quoteOfPrice(price) {
  return quoteOf(price, currentVatRate())
}

export function isFreePrice(price) {
  return Number(price) === 0
}

export function pricingOfPrice(price, availability) {
  if (price === null || price === undefined) return { ...UNSET, availability }
  const isFree = isFreePrice(price)
  return { price: isFree ? null : quoteOfPrice(price), isFree, availability }
}

export function pathPricingOf(key, now = Date.now()) {
  const version = inForceVersion(key, now)
  return version ? pricingOfPrice(version.priceBeforeVat, version.availability) : { ...UNSET }
}

export function priceInForce(key) {
  return inForceVersion(key)?.priceBeforeVat ?? null
}

export function availabilityOf(key) {
  return pathPricingOf(key).availability
}
