import { useI18n } from 'vue-i18n'
import { useConfirm } from '@/composables/useConfirm'
import { toast } from '@/composables/useToast'
import { criteriaService } from '@/services/criteriaService'

export function useDraftLifecycle() {
  const { t } = useI18n()
  const { confirm } = useConfirm()

  async function createDraft({ fromPublished }) {
    try {
      const { data } = await criteriaService.createDraft({})
      toast.success(
        t(fromPublished ? 'criteria.draft.created' : 'criteria.draft.createdEmpty', {
          number: data.versionNumber,
        }),
      )
      return data
    } catch (error) {
      toast.error(error?.normalized?.message || t('criteria.toasts.saveFailed'))
      return null
    }
  }

  async function discardDraft(draft) {
    const accepted = await confirm({
      title: t('criteria.draft.discardTitle', { number: draft.versionNumber }),
      message: t('criteria.draft.discardMessage'),
      confirmLabel: t('actions.delete'),
      variant: 'danger',
    })
    if (!accepted) return false
    try {
      await criteriaService.discardDraft(draft.id)
      toast.success(t('criteria.draft.discarded', { number: draft.versionNumber }))
      return true
    } catch (error) {
      toast.error(error?.normalized?.message || t('criteria.toasts.deleteFailed'))
      return false
    }
  }

  return { createDraft, discardDraft }
}
