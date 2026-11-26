import { computed, ref } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useLoginOutcome } from '@/composables/useLoginOutcome'
import { logger } from '@/utils/logger'
import { firstFieldErrors } from '@/utils/payments/financeErrors'
import { codeRequestFailureOf } from '@/utils/auth/authFailures'
import {
  SIGN_IN_CODE_DEFAULTS,
  signInCodeRequestOf,
  signInCodeSchema,
} from '@/utils/auth/signInForm'
import { AUTH_FLOW_INTENTS } from '@/constants/loginSteps'

const ACTION = 'auth.otp.request'

export function useSignInCodeRequest(initialIdentifier = '') {
  const { t } = useI18n()
  const auth = useAuthStore()
  const { continueAfter } = useLoginOutcome()
  const failure = ref(null)
  const submitting = ref(false)
  const form = useForm({
    validationSchema: computed(() => signInCodeSchema(t)),
    initialValues: { ...SIGN_IN_CODE_DEFAULTS, identifier: initialIdentifier },
  })

  function reject(error) {
    const fieldErrors = firstFieldErrors(error)
    if (Object.keys(fieldErrors).length) {
      form.setErrors(fieldErrors)
      return
    }
    failure.value = codeRequestFailureOf(error)
    logger.breadcrumb('auth', 'auth.otp.request.rejected', { failure: failure.value.kind })
  }

  async function request(values) {
    if (submitting.value) return
    submitting.value = true
    failure.value = null
    logger.breadcrumb('form', ACTION, { fields: Object.keys(SIGN_IN_CODE_DEFAULTS) })
    try {
      const outcome = await auth.requestSignInCode(signInCodeRequestOf(values))
      await continueAfter(outcome, {
        officialEmail: '',
        identifier: values.identifier.trim(),
        intent: AUTH_FLOW_INTENTS.CODE,
      })
    } catch (error) {
      reject(error)
    } finally {
      submitting.value = false
    }
  }

  return { form, failure, submitting, onSubmit: form.handleSubmit(request) }
}
