import { decideRequest, itemsOf, pendingRequestOf } from '@/mocks/stores/profileChangeRequests'
import { findEntity } from '@/mocks/stores/entitiesStore'

const APPROVER = { id: 2, roles: ['system_admin'], name: { ar: 'منيرة', en: 'Munira' } }

/** Approves every item of the entity's pending profile change request, as staff would. */
export function approvePendingChange(entityId) {
  const request = pendingRequestOf(entityId)
  if (!request) throw new Error(`Entity ${entityId} has no pending profile change.`)
  return decideRequest(
    request,
    findEntity(entityId),
    { revision: request.revision, approvedItems: itemsOf(request) },
    APPROVER,
  )
}
