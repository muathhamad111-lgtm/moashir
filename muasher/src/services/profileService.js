import { apiClient } from '@/api/client'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'

function toRole(role) {
  return { key: role.key, label: role.label }
}

function toPermission(permission) {
  return { key: permission.key, module: permission.module, label: permission.label }
}

export const profileService = {
  async getMyPermissions() {
    const envelope = await serviceRequest(
      'profile.getMyPermissions',
      () => apiClient.get('/me/profile/permissions'),
      { entity: 'profile-permissions' },
    )
    const data = envelope.data ?? {}
    return {
      ...envelope,
      data: {
        roles: (data.roles ?? []).map(toRole),
        permissions: (data.permissions ?? []).map(toPermission),
      },
    }
  },
  updatePreferences(preferences) {
    return serviceRequest(
      'profile.updatePreferences',
      () => apiClient.patch('/me/preferences', preferences),
      {
        entity: 'preferences',
        payloadShape: payloadShape(preferences),
      },
    )
  },
}
