import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { advancedService } from '@/services/advancedService'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

export function useDraftDeletion() {
  const { t } = useI18n()
  const { confirm } = useConfirm()
  const toast = useToast()
  const deleting = ref(false)

  async function deleteDraft(request) {
    if (deleting.value) return false
    const accepted = await confirm({
      title: t('advanced.draft.deleteTitle'),
      message: request.number,
      variant: 'danger',
      confirmLabel: t('actions.delete'),
    })
    if (!accepted) return false
    deleting.value = true
    try {
      await advancedService.deleteDraft(request.id)
      toast.success(t('advanced.draft.deleted'))
      return true
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
      return false
    } finally {
      deleting.value = false
    }
  }

  return { deleting, deleteDraft }
}
