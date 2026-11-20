import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { authService } from '@/services/authService'
import { changePasswordPayload, changePasswordSchema } from '@/utils/auth/passwordForms'

const EMPTY = { currentPassword: '', password: '', passwordConfirmation: '' }

// The signed-in user changes their own password on the account page, without leaving it.
export function useOwnPasswordChange() {
  const { t } = useI18n()
  const saved = ref(false)
  const form = useValidatedForm({
    schema: computed(() => changePasswordSchema(t)),
    initialValues: { ...EMPTY },
    action: 'account.password.submit',
    submit: async (values) => {
      saved.value = false
      await authService.changePassword(changePasswordPayload(values))
      form.resetForm({ values: { ...EMPTY } })
      saved.value = true
    },
  })
  return { form, saved }
}
