import { ACCREDITED_STATUSES } from '@/constants/accredited'

export const ISSUING_STAGES = Object.freeze({ REVIEW: 'review', ISSUING: 'issuing' })

function activeEntry(active) {
  if (!active || active.status === ACCREDITED_STATUSES.COMPLETED) return null
  return {
    id: active.id,
    number: active.number,
    status: active.status,
    since: active.createdAt,
    stage: ISSUING_STAGES.REVIEW,
  }
}

function completedEntry(request) {
  return {
    id: request.id,
    number: request.number,
    status: request.status,
    since: request.completedAt ?? request.createdAt,
    stage: ISSUING_STAGES.ISSUING,
  }
}

export function issuingRequestsOf({ active = null, completed = [], certifiedRequestIds = [] }) {
  const current = activeEntry(active)
  const awaiting = completed
    .filter((request) => !certifiedRequestIds.includes(request.id) && request.id !== current?.id)
    .map(completedEntry)
  return current ? [current, ...awaiting] : awaiting
}
