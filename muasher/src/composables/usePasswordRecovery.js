import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { authService } from '@/services/authService'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { AUTH_NOTICES } from '@/constants/loginSteps'
import { forgotPasswordSchema, resetPasswordSchema, resetPayload } from '@/utils/auth/passwordForms'

const INVALID_TOKEN = 'token.invalid'

export function useForgotPassword() {
  const { t } = useI18n()
  const sent = ref(false)
  const form = useValidatedForm({
    schema: computed(() => forgotPasswordSchema(t)),
    initialValues: { email: '' },
    action: 'auth.forgotPassword.submit',
    submit: async (values) => {
      await authService.requestPasswordReset({ email: values.email.trim() })
      sent.value = true
    },
  })
  return { form, sent }
}

export function usePasswordReset(token) {
  const { t } = useI18n()
  const router = useRouter()
  const tokenInvalid = ref(false)
  const form = useValidatedForm({
    schema: computed(() => resetPasswordSchema(t)),
    initialValues: { email: '', password: '', passwordConfirmation: '' },
    action: 'auth.resetPassword.submit',
    submit: async (values) => {
      try {
        await authService.resetPassword(resetPayload(token.value, values))
      } catch (error) {
        if (error?.normalized?.code !== INVALID_TOKEN) throw error
        tokenInvalid.value = true
        return
      }
      await router.push({
        name: ROUTE_NAMES.AUTH_LOGIN,
        query: { notice: AUTH_NOTICES.PASSWORD_RESET },
      })
    },
  })
  return { form, tokenInvalid }
}
