import { computed, ref } from 'vue'
import { permissionsService } from '@/services/permissionsService'
import { labelsChanged, matrixChanges, rolesHolding } from '@/utils/permissionMatrix'
import { ROLES } from '@/constants/roles'

const NOT_EDITABLE_ROLES = [ROLES.ENTITY]

export function usePermissionMatrix() {
  const permissions = ref([])
  const roles = ref([])
  const matrix = ref({})
  const status = ref('idle')

  const editableRoles = computed(() =>
    roles.value.filter((role) => !NOT_EDITABLE_ROLES.includes(role.key)).map((role) => role.key),
  )

  const rows = computed(() =>
    permissions.value.map((permission) => ({ ...permission, roles: rolesHolding(matrix.value, permission.key) })),
  )

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await permissionsService.getMatrix()
      permissions.value = data.permissions
      roles.value = data.roles
      matrix.value = data.matrix
      status.value = 'success'
    } catch (error) {
      status.value = 'error'
      throw error
    }
  }

  async function save(permission, changes) {
    if (labelsChanged(permission, changes)) {
      await permissionsService.updateLabels(permission.key, {
        label: changes.label,
        description: changes.description,
      })
    }
    const updates = matrixChanges(matrix.value, permission.key, changes.roles, editableRoles.value)
    for (const update of updates) {
      await permissionsService.updateRolePermissions(update.role, update.keys)
    }
    await load()
  }

  return { permissions, roles, matrix, rows, status, editableRoles, load, save }
}
