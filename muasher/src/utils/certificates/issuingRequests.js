import { SUBMITTED_ACCREDITED_STATUSES } from '@/constants/levels'

export const ISSUING_STAGES = Object.freeze({ REVIEW: 'review', ISSUING: 'issuing' })

// Only a sent request is on its way to a certificate; an unsent draft is not.
function activeEntry(active) {
  if (!SUBMITTED_ACCREDITED_STATUSES.includes(active?.status)) return null
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
