import { reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { identityService } from '@/services/identityService'
import { USER_ACTIONS } from '@/constants/users'

export function useUserIdentityActions() {
  const { t } = useI18n()
  const toast = useToast()
  const correction = reactive({ open: false, user: null })
  const history = reactive({ open: false, user: null, status: 'idle', items: [] })

  async function loadHistory(user) {
    history.status = 'loading'
    try {
      const { data } = await identityService.userHistory(user.id)
      history.items = data ?? []
      history.status = 'success'
    } catch {
      history.status = 'error'
    }
  }

  function open(key, user) {
    if (key === USER_ACTIONS.NAME_CORRECTION) {
      correction.user = user
      correction.open = true
      return
    }
    history.user = user
    history.open = true
    loadHistory(user)
  }

  async function submitCorrection(payload) {
    await identityService.correctName(correction.user.id, payload)
    toast.success(t('nationalIdentity.correction.submitted'))
    return true
  }

  return { correction, history, open, submitCorrection, loadHistory }
}
