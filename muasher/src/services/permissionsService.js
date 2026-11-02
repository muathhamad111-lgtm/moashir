import { apiClient } from '@/api/client'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'

function toMatrix(data = {}) {
  return {
    permissions: data.permissions ?? [],
    roles: data.roles ?? [],
    matrix: data.matrix ?? {},
  }
}

export const permissionsService = {
  async getMatrix(params = {}) {
    const envelope = await serviceRequest(
      'permissions.getMatrix',
      () => apiClient.get('/permissions', { params }),
      { entity: 'permission', paramsShape: payloadShape(params) },
    )
    return { ...envelope, data: toMatrix(envelope.data) }
  },
  updateLabels(key, { label, description }) {
    const payload = { label, description }
    return serviceRequest(
      'permissions.updateLabels',
      () => apiClient.put(`/permissions/${encodeURIComponent(key)}`, payload),
      { entity: 'permission', payloadShape: payloadShape(payload) },
    )
  },
  updateRolePermissions(role, permissionKeys) {
    const payload = { permissionKeys }
    return serviceRequest(
      'permissions.updateRolePermissions',
      () => apiClient.put(`/roles/${encodeURIComponent(role)}/permissions`, payload),
      { entity: 'role', payloadShape: payloadShape(payload) },
    )
  },
}
