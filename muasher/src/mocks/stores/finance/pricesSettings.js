import { riyadhDateKey, startOfRiyadhDay } from '@/utils/datetime'
import { paginate } from '../../contract/pagination'
import { MockRejection } from '../../contract/rejection'
import { actorDisplayName } from '../actorName'
import { financeState, persistFinanceState } from './financeState'
import { requirePermission } from './financeAccess'
import { fieldMessage, financeReject } from './financeRejection'
import {
  PRICE_KEYS,
  findPriceVersion,
  inForceVersion,
  isScheduled,
  pathPricingOf,
  quoteOfPrice,
  scheduledVersion,
  versionsOf,
} from './priceVersions'

export const PRICES_PERMISSION = 'settings.prices.manage'
const PRICE_PATTERN = /^\d{1,7}\.\d{2}$/
const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/
const AVAILABILITY = ['available', 'paused', 'hidden']
const PRICE_MAX = '9999999.99'
const DAY_MS = 24 * 60 * 60 * 1000
const HORIZON_DAYS = 365
const HISTORY_PER_PAGE = 20
const HISTORY_MAX_PER_PAGE = 50
const REASON_MIN = 10
const REASON_MAX = 1000

export function authorizePrices(user) {
  requirePermission(user, PRICES_PERMISSION)
}

export function knownPriceKey(key) {
  if (!PRICE_KEYS.includes(key)) throw financeReject.notFound()
  return key
}

function personOf(person) {
  return person ? { id: person.id, name: person.name } : null
}

function versionResource(version, now = Date.now()) {
  return {
    id: version.id,
    priceBeforeVat: version.priceBeforeVat,
    currency: 'SAR',
    availability: version.availability,
    effectiveFrom: version.effectiveFrom,
    effectiveOn: riyadhDateKey(version.effectiveFrom),
    isScheduled: isScheduled(version, now),
    setBy: personOf(version.setBy),
    createdAt: version.createdAt,
  }
}

function quotedVersion(version) {
  return version
    ? { ...versionResource(version), quote: quoteOfPrice(version.priceBeforeVat) }
    : null
}

export function priceLimits(now = Date.now()) {
  return {
    priceMax: PRICE_MAX,
    effectiveFromMin: riyadhDateKey(new Date(now + DAY_MS)),
    effectiveFromMax: riyadhDateKey(new Date(now + HORIZON_DAYS * DAY_MS)),
  }
}

export function priceRowResource(key) {
  return {
    key,
    pricing: pathPricingOf(key),
    inForce: quotedVersion(inForceVersion(key)),
    scheduled: quotedVersion(scheduledVersion(key)),
    historyTotal: versionsOf(key).length,
  }
}

export function pricesSettingsResource() {
  return {
    currency: 'SAR',
    vatRate: financeState().settings.vatRate,
    rows: PRICE_KEYS.map(priceRowResource),
    limits: priceLimits(),
  }
}

function stateOf(version, inForce, now) {
  if (version.cancelledAt) return 'cancelled'
  if (isScheduled(version, now)) return 'scheduled'
  return version.id === inForce?.id ? 'in_force' : 'superseded'
}

function beforeOf(version) {
  const previous = version.previousVersionId ? findPriceVersion(version.previousVersionId) : null
  return previous
    ? { priceBeforeVat: previous.priceBeforeVat, availability: previous.availability }
    : null
}

function historyItem(version, inForce, now) {
  return {
    ...versionResource(version, now),
    state: stateOf(version, inForce, now),
    cancelledAt: version.cancelledAt,
    cancelledBy: personOf(version.cancelledBy),
    before: beforeOf(version),
  }
}

function newestCreated(left, right) {
  return new Date(right.createdAt) - new Date(left.createdAt) || right.id - left.id
}

function historyQuery(query) {
  const perPage = Math.min(HISTORY_MAX_PER_PAGE, Number(query.get('perPage') ?? HISTORY_PER_PAGE))
  const bounded = new URLSearchParams(query)
  bounded.set('perPage', String(perPage > 0 ? perPage : HISTORY_PER_PAGE))
  return bounded
}

export function priceHistory(key, query) {
  const now = Date.now()
  const inForce = inForceVersion(key, now)
  const items = [...versionsOf(key)]
    .sort(newestCreated)
    .map((version) => historyItem(version, inForce, now))
  return paginate(items, historyQuery(query), HISTORY_PER_PAGE)
}

function priceError(value) {
  if (value === undefined || value === null || value === '') return fieldMessage('required')
  return typeof value === 'string' && PRICE_PATTERN.test(value)
    ? null
    : fieldMessage('price_format')
}

function availabilityError(value) {
  if (!value) return fieldMessage('required')
  return AVAILABILITY.includes(value) ? null : fieldMessage('choice')
}

function effectiveError(value) {
  if (value === undefined || value === null || value === '') return null
  const { effectiveFromMin: min, effectiveFromMax: max } = priceLimits()
  if (typeof value !== 'string' || !DATE_PATTERN.test(value)) return fieldMessage('date')
  return value < min || value > max ? fieldMessage('price_date_range', { min, max }) : null
}

function baseError(body) {
  if (!Object.hasOwn(body, 'baseVersionId')) return fieldMessage('required')
  const value = body.baseVersionId
  return value === null || Number.isInteger(value) ? null : fieldMessage('invalid')
}

function reasonError(value) {
  if (value === undefined || value === null || value === '') return null
  const reason = String(value).trim()
  if (reason.length < REASON_MIN) return fieldMessage('min', { min: REASON_MIN })
  return reason.length > REASON_MAX ? fieldMessage('max', { max: REASON_MAX }) : null
}

function assertValid(body) {
  const errors = [
    ['priceBeforeVat', priceError(body.priceBeforeVat)],
    ['availability', availabilityError(body.availability)],
    ['effectiveFrom', effectiveError(body.effectiveFrom)],
    ['baseVersionId', baseError(body)],
    ['reason', reasonError(body.reason)],
  ].filter(([, message]) => message)
  if (errors.length)
    throw financeReject.validation(
      Object.fromEntries(errors.map(([field, message]) => [field, [message]])),
    )
}

function assertReason(user, body) {
  if (user.superAdmin && !String(body.reason ?? '').trim()) throw financeReject.reasonRequired()
}

function assertFresh(key, body) {
  const current = inForceVersion(key)?.id ?? null
  if (body.baseVersionId === current) return
  throw new MockRejection(409, 'prices.stale_version', { meta: { row: priceRowResource(key) } })
}

function actorOf(user) {
  return { id: user.id, name: actorDisplayName(user, null) }
}

function cancelPending(key, user, at) {
  const pending = scheduledVersion(key)
  if (pending) Object.assign(pending, { cancelledAt: at, cancelledBy: actorOf(user) })
  return pending?.id ?? null
}

function sameAsInForce(key, body) {
  const current = inForceVersion(key)
  return (
    Boolean(current) &&
    !body.effectiveFrom &&
    current.priceBeforeVat === body.priceBeforeVat &&
    current.availability === body.availability
  )
}

function nextVersionId() {
  const counters = financeState().counters
  counters.priceVersion = (counters.priceVersion ?? 0) + 1
  return counters.priceVersion
}

function appendVersion(key, user, body, at) {
  financeState().priceVersions.push({
    id: nextVersionId(),
    key,
    priceBeforeVat: body.priceBeforeVat,
    availability: body.availability,
    effectiveFrom: body.effectiveFrom ? startOfRiyadhDay(body.effectiveFrom) : at,
    previousVersionId: inForceVersion(key)?.id ?? null,
    setBy: actorOf(user),
    createdAt: at,
    cancelledAt: null,
    cancelledBy: null,
  })
}

export function writePrice(user, key, body) {
  assertValid(body)
  assertReason(user, body)
  assertFresh(key, body)
  if (sameAsInForce(key, body)) return { changed: false, cancelledVersionId: null }
  const at = new Date().toISOString()
  const cancelledVersionId = cancelPending(key, user, at)
  appendVersion(key, user, body, at)
  persistFinanceState()
  return { changed: true, cancelledVersionId }
}

export function cancelScheduledPrice(user, key, body) {
  const reason = reasonError(body.reason)
  if (reason) throw financeReject.validation({ reason: [reason] })
  assertReason(user, body)
  if (!scheduledVersion(key)) throw new MockRejection(404, 'prices.no_scheduled_version')
  cancelPending(key, user, new Date().toISOString())
  persistFinanceState()
}
