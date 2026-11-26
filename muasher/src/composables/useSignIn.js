import { computed, ref } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useLoginOutcome } from '@/composables/useLoginOutcome'
import { logger } from '@/utils/logger'
import { firstFieldErrors } from '@/utils/payments/financeErrors'
import { signInFailureOf } from '@/utils/auth/authFailures'
import {
  SIGN_IN_DEFAULTS,
  signInCredentials,
  signInEmailOf,
  signInSchema,
} from '@/utils/auth/signInForm'
import { AUTH_FLOW_INTENTS } from '@/constants/loginSteps'

const ACTION = 'auth.login.submit'

export function useSignIn(initialIdentifier = '') {
  const { t } = useI18n()
  const auth = useAuthStore()
  const { continueAfter } = useLoginOutcome()
  const failure = ref(null)
  const submitting = ref(false)
  const form = useForm({
    validationSchema: computed(() => signInSchema(t)),
    initialValues: { ...SIGN_IN_DEFAULTS, identifier: initialIdentifier },
  })

  function reject(error) {
    const fieldErrors = firstFieldErrors(error)
    if (Object.keys(fieldErrors).length) {
      form.setErrors(fieldErrors)
      return
    }
    failure.value = signInFailureOf(error)
    form.setFieldValue('password', '', false)
    logger.breadcrumb('auth', 'auth.login.rejected', { failure: failure.value.kind })
  }

  async function signIn(values) {
    if (submitting.value) return
    submitting.value = true
    failure.value = null
    logger.breadcrumb('form', ACTION, { fields: Object.keys(SIGN_IN_DEFAULTS) })
    try {
      const credentials = signInCredentials(values)
      const outcome = await auth.signIn(credentials)
      await continueAfter(outcome, {
        officialEmail: signInEmailOf(credentials.identifier),
        identifier: values.identifier.trim(),
        intent: AUTH_FLOW_INTENTS.LOGIN,
      })
    } catch (error) {
      reject(error)
    } finally {
      submitting.value = false
    }
  }

  return { form, failure, submitting, onSubmit: form.handleSubmit(signIn) }
}
