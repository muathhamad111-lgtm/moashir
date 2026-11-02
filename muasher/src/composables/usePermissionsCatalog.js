import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PERMISSION_MODULES } from '@/constants/permissionKeys'

export function usePermissionsCatalog() {
  const { t, locale } = useI18n()

  function localized(value) {
    if (value === null || value === undefined) return ''
    if (typeof value === 'string') return value
    return locale.value === 'en' ? value.en || value.ar : value.ar || value.en
  }

  function groupLabel(module) {
    return module ? t(`permissions.groups.${module}`) : ''
  }

  function roleLabel(role) {
    return role ? t(`users.roleLabels.${role}`) : ''
  }

  const groupFilterOptions = computed(() => [
    { value: '', label: t('permissions.filters.allGroups') },
    ...PERMISSION_MODULES.map((module) => ({ value: module, label: groupLabel(module) })),
  ])

  return { localized, groupLabel, roleLabel, groupFilterOptions }
}
