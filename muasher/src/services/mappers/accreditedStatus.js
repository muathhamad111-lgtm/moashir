import { ACCREDITED_STATUSES, COMPLETION_STATUSES } from '@/constants/accredited'
import { fromCanonicalStatus } from '@/utils/canonicalStatus'

const WIRE_DECLINED = 'declined-incomplete'

const REQUEST_FROM_WIRE = { [WIRE_DECLINED]: ACCREDITED_STATUSES.DECLINED }
const REQUEST_TO_WIRE = { [ACCREDITED_STATUSES.DECLINED]: WIRE_DECLINED }

const COMPLETION_FROM_WIRE = {
  'expired-continued': COMPLETION_STATUSES.EXPIRED_CONTINUED,
  'expired-declined': COMPLETION_STATUSES.EXPIRED_DECLINED,
}

export function toRequestStatus(value) {
  const token = fromCanonicalStatus(value ?? null)
  return REQUEST_FROM_WIRE[token] ?? token
}

export function toWireRequestStatus(value) {
  return REQUEST_TO_WIRE[value] ?? value
}

export function toCompletionStatus(value) {
  const token = fromCanonicalStatus(value ?? null)
  return COMPLETION_FROM_WIRE[token] ?? token
}

function camelOfToken(token) {
  return token.replace(/-([a-z0-9])/g, (match, letter) => letter.toUpperCase())
}

export function toStatusCounts(raw, statuses) {
  return Object.fromEntries(statuses.map((status) => [status, Number(raw?.[camelOfToken(toWireRequestStatus(status))] ?? 0)]))
}
