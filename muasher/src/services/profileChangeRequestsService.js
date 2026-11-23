import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { withOwnEntity } from './mappers/ownEntity'

const STAFF_PATH = '/admin/entity-profile-changes'
const OWN_PATH = '/entities/me'
const NOT_FOUND = 404

const { read, write } = domainRequests('profileChangeRequests', 'profile-change')

export const profileChangeRequestsService = {
  list(params = {}) {
    return read('list', () => apiClient.get(STAFF_PATH, { params }), params)
  },
  show(id) {
    return read('show', () => apiClient.get(`${STAFF_PATH}/${encodeURIComponent(id)}`))
  },
  decide(id, decision) {
    return write(
      'decide',
      () => apiClient.post(`${STAFF_PATH}/${encodeURIComponent(id)}/decide`, decision),
      decision,
    )
  },
  ownPending() {
    return read('ownPending', () => apiClient.get(`${OWN_PATH}/profile-change`))
  },
  ownList(params = {}) {
    return read(
      'ownList',
      () => apiClient.get(`${OWN_PATH}/profile-change-requests`, { params }),
      params,
    )
  },
  cancelOwn() {
    return write('cancelOwn', () => apiClient.post(`${OWN_PATH}/profile-change/cancel`, {}), {}, [
      NOT_FOUND,
    ]).then(withOwnEntity)
  },
}
