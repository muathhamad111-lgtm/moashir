import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConfirm } from '@/composables/useConfirm'
import { useSuccess } from '@/composables/useSuccess'
import { useToast } from '@/composables/useToast'
import { useEntityStore } from '@/stores/entity'
import { logger } from '@/utils/logger'

const ACTION = 'entities.emailChange.cancel'
const FORBIDDEN_STATUS = 403

export function useEmailChangeCancel() {
  const { t } = useI18n()
  const { confirm } = useConfirm()
  const { showSuccess } = useSuccess()
  const toast = useToast()
  const store = useEntityStore()
  const busy = ref(false)

  async function cancelRequest() {
    if (busy.value) return
    const accepted = await confirm({
      title: t('portal.profile.email.cancelTitle'),
      message: t('portal.profile.email.cancelMessage'),
      confirmLabel: t('portal.profile.email.cancelConfirm'),
      cancelLabel: t('portal.profile.email.keepRequest'),
      variant: 'warning',
    })
    if (!accepted) return
    busy.value = true
    logger.breadcrumb('form', ACTION, {})
    try {
      await store.cancelEmailChange()
      showSuccess({
        title: t('portal.profile.email.cancelledTitle'),
        message: t('portal.profile.email.cancelMessage'),
      })
    } catch (error) {
      logger.error(error, { action: ACTION })
      toast.error(error?.normalized?.message || t('errors.unexpected'))
      if (error?.response?.status === FORBIDDEN_STATUS)
        await store
          .loadProfile({ force: true })
          .catch((failure) => logger.error(failure, { action: ACTION }))
    } finally {
      busy.value = false
    }
  }

  return { busy, cancelRequest }
}
