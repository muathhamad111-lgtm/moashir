import { apiClient } from '@/api/client'
import { SEARCH_TYPES } from '@/constants/globalSearch'
import { isKnownRole } from '@/constants/roles'
import { serviceRequest } from './serviceRequest'

const ITEM_MAPPERS = {
  [SEARCH_TYPES.entities]: (raw) => ({
    id: raw.id,
    title: raw.name ?? '',
    status: raw.status ?? null,
  }),
  [SEARCH_TYPES.requests]: (raw) => ({
    id: raw.id,
    title: raw.entity?.name ?? '',
    reference: raw.number ?? '',
    path: raw.path ?? null,
    pathLabel: raw.pathLabel ?? null,
    status: raw.status ?? null,
    isFrozen: raw.isFrozen === true,
  }),
  [SEARCH_TYPES.users]: (raw) => ({
    id: raw.id,
    title: raw.name ?? '',
    email: raw.email ?? '',
    roles: (raw.roles ?? []).filter(isKnownRole),
    status: raw.status ?? null,
  }),
}

function toItem(type, raw) {
  return { type, key: `${type}:${raw.id}`, ...ITEM_MAPPERS[type](raw) }
}

function toGroup(group) {
  return {
    type: group.type,
    total: group.total ?? group.items?.length ?? 0,
    items: (group.items ?? []).map((raw) => toItem(group.type, raw)),
  }
}

function toResults(data) {
  const groups = Array.isArray(data) ? data : []
  return { groups: groups.filter((group) => group.type in ITEM_MAPPERS).map(toGroup) }
}

export const searchService = {
  async search(query) {
    const envelope = await serviceRequest(
      'search.global',
      () => apiClient.get('/search', { params: { q: query } }),
      { entity: 'global-search', queryLength: query.length },
    )
    return { ...envelope, data: toResults(envelope.data) }
  },
}
