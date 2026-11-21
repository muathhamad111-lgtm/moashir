import { computed, onMounted, ref } from 'vue'
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

function isInvalidToken(error) {
  return error?.normalized?.code === INVALID_TOKEN
}

// The link alone names the account: the page checks it on open, then sends only the new password.
export function usePasswordReset(token) {
  const { t } = useI18n()
  const router = useRouter()
  const status = ref('loading')

  async function load() {
    status.value = 'loading'
    try {
      await authService.checkPasswordResetLink(token.value)
      status.value = 'ready'
    } catch (error) {
      status.value = isInvalidToken(error) ? 'invalid' : 'error'
    }
  }

  const form = useValidatedForm({
    schema: computed(() => resetPasswordSchema(t)),
    initialValues: { password: '', passwordConfirmation: '' },
    action: 'auth.resetPassword.submit',
    submit: async (values) => {
      try {
        await authService.resetPassword(resetPayload(token.value, values))
      } catch (error) {
        if (!isInvalidToken(error)) throw error
        status.value = 'invalid'
        return
      }
      await router.push({
        name: ROUTE_NAMES.AUTH_LOGIN,
        query: { notice: AUTH_NOTICES.PASSWORD_RESET },
      })
    },
  })

  onMounted(load)

  return { form, status, load }
}
