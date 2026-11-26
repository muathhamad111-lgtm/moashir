import { PROFILE_CHANGE_VERDICTS as V } from '@/constants/entityEnums'
import { findEntity } from './entitiesStore'
import { applyApprovedItems } from './profileChangeApply'
import { snapshotOf } from './entitySectionSaves'
import { recordProfileChanges } from './profileChangesStore'

export const PROFILE_CHANGES_STORAGE_KEY = 'moashir_mock_profile_changes'

// Kept across reloads in the browser (as the finance mock is), so an e2e journey can submit as the
// entity, decide as staff and come back; unit tests start from an empty store every time.
const PERSISTED = import.meta.env.MODE !== 'test'

function storage() {
  return PERSISTED && typeof sessionStorage !== 'undefined' ? sessionStorage : null
}

export function persistRequests(requests) {
  storage()?.setItem(PROFILE_CHANGES_STORAGE_KEY, JSON.stringify(requests))
}

function approvedOf(request) {
  return Object.keys(request.decisions ?? {}).filter(
    (item) => request.decisions[item] === V.APPROVED,
  )
}

function replay(request) {
  const entity = findEntity(request.entityId)
  if (!entity) return
  const before = snapshotOf(entity)
  applyApprovedItems(entity, request, approvedOf(request))
  recordProfileChanges(entity, before, entity, { actor: null, isStaff: false })
}

/** The stored requests, with the items staff approved written back onto the fresh entity fixtures. */
export function restoredRequests() {
  const raw = storage()?.getItem(PROFILE_CHANGES_STORAGE_KEY)
  if (!raw) return []
  try {
    const requests = JSON.parse(raw)
    if (!Array.isArray(requests)) return []
    requests
      .filter((request) => request.decidedAt)
      .sort((left, right) => left.decidedAt.localeCompare(right.decidedAt))
      .forEach(replay)
    return requests
  } catch {
    return []
  }
}
