import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { USER_ACTIONS } from '@/constants/users'

export function useUserPathAccess({ onSaved }) {
  const { t } = useI18n()
  const toast = useToast()
  const action = ref(null)
  const user = ref(null)

  function dialogFor(key) {
    return computed({
      get: () => action.value === key,
      set: (open) => {
        if (!open) action.value = null
      },
    })
  }

  function open(key, row) {
    user.value = row
    action.value = key
  }

  function saved(updated) {
    toast.success(t('users.toasts.pathAccess', { name: updated.name }))
    onSaved(updated)
  }

  return {
    user,
    open,
    saved,
    qualificationsOpen: dialogFor(USER_ACTIONS.QUALIFICATIONS),
    scopeOpen: dialogFor(USER_ACTIONS.REQUEST_SCOPE),
  }
}
