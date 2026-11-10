import { reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { entityLifecycleService } from '@/services/entityLifecycleService'
import { downloadJson } from '@/utils/download'
import { localizedText } from '@/utils/localizedText'
import { ENTITY_ACTIONS as A } from '@/composables/useEntityActions'

const REASON_ACTIONS = {
  [A.DEACTIVATE]: (entity, reason) => entityLifecycleService.deactivate(entity.id, reason),
  [A.REACTIVATE]: (entity, reason) => entityLifecycleService.reactivate(entity.id, reason),
  [A.RESTORE]: (entity, reason) => entityLifecycleService.restore(entity.id, reason),
  [A.EXPORT_PERSONAL_DATA]: async (entity, reason) => {
    const { data } = await entityLifecycleService.exportPersonalData(entity.id, reason)
    downloadJson(data, `${entity.reference}-personal-data.json`)
    return data
  },
}

const DIALOG_ACTIONS = new Set([A.DELETE, A.PERMANENT_DELETE, A.EDIT])

export function useEntityActionRunner({ onDone }) {
  const { t, locale } = useI18n()
  const toast = useToast()
  const { confirm } = useConfirm()
  const state = reactive({ entity: null, reasonKey: null, reasonOpen: false, dialog: null })

  function finish(key, entity, result) {
    toast.success(t(`entities.toasts.${key}`))
    onDone({ key, entity, result })
  }

  async function resendVerification(entity) {
    const accepted = await confirm({
      title: t('entities.confirm.resendVerificationTitle'),
      message: localizedText(entity.name, locale.value),
      confirmLabel: t('actions.send'),
    })
    if (!accepted) return
    try {
      await entityLifecycleService.resendVerification(entity.id)
      finish(A.RESEND_VERIFICATION, entity)
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
    }
  }

  function run(key, entity) {
    state.entity = entity
    if (key === A.RESEND_VERIFICATION) return resendVerification(entity)
    if (REASON_ACTIONS[key]) {
      state.reasonKey = key
      state.reasonOpen = true
    } else if (DIALOG_ACTIONS.has(key)) {
      state.dialog = key
    }
  }

  function reasonAction(reason) {
    return REASON_ACTIONS[state.reasonKey](state.entity, reason)
  }

  function closeDialog() {
    state.dialog = null
  }

  return { state, run, reasonAction, finish, closeDialog }
}
