import { computed } from 'vue'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { CRITERIA_VERSION_STATUSES } from '@/constants/criteria'

export function useCriteriaAccess(version) {
  const { can } = usePermissions()
  const canManage = computed(() => can(PERMISSIONS.CRITERIA_MANAGE))
  const canPublish = computed(() => can(PERMISSIONS.CRITERIA_PUBLISH))
  const isDraft = computed(() => version.value?.status === CRITERIA_VERSION_STATUSES.DRAFT)
  const isFrozen = computed(() => Boolean(version.value) && !isDraft.value)

  return {
    canManage,
    canPublish,
    isDraft,
    isFrozen,
    canEditTree: computed(() => isDraft.value && canManage.value),
    canCorrectText: computed(() => isFrozen.value && canManage.value),
    canPublishDraft: computed(() => isDraft.value && canPublish.value),
  }
}
