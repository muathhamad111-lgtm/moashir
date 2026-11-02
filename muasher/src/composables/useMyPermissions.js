import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { profileService } from '@/services/profileService'
import { PERMISSION_MODULES } from '@/constants/permissionKeys'

function resolve(value, locale) {
  if (value === null || value === undefined) return ''
  if (typeof value === 'string') return value
  return value[locale] || value.ar || value.en || ''
}

function moduleOrder(module) {
  const index = PERMISSION_MODULES.indexOf(module)
  return index === -1 ? PERMISSION_MODULES.length : index
}

export function groupPermissionsByModule(permissions) {
  const groups = new Map()
  permissions.forEach((permission) => {
    if (!groups.has(permission.module)) groups.set(permission.module, [])
    groups.get(permission.module).push(permission)
  })
  return [...groups.entries()]
    .map(([module, items]) => ({ module, items }))
    .sort((first, second) => moduleOrder(first.module) - moduleOrder(second.module))
}

export function useMyPermissions({ auto = true } = {}) {
  const { locale } = useI18n()
  const roles = ref([])
  const permissions = ref([])
  const status = ref('loading')

  const modules = computed(() => groupPermissionsByModule(permissions.value))

  function localized(value) {
    return resolve(value, locale.value)
  }

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await profileService.getMyPermissions()
      roles.value = data.roles
      permissions.value = data.permissions
      status.value = data.roles.length || data.permissions.length ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  if (auto) onMounted(load)

  return { roles, permissions, modules, status, load, localized }
}
