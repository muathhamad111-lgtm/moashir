import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useOtpChallenge } from '@/composables/useOtpChallenge'
import { useToast } from '@/composables/useToast'
import { entitiesService } from '@/services/entitiesService'
import { emailChangeSchema } from '@/utils/entitySchemas'
import { logger } from '@/utils/logger'

export const EMAIL_CHANGE_STEPS = { EMAIL: 'email', CODE: 'code', DONE: 'done' }

const VERIFY_ACTION = 'entities.emailChange.verify'

export function useEmailChange({ onCompleted }) {
  const { t } = useI18n()
  const toast = useToast()
  const otp = useOtpChallenge()
  const step = ref(EMAIL_CHANGE_STEPS.EMAIL)
  const target = ref('')
  const code = ref('')
  const codeError = ref('')
  const verifying = ref(false)
  const result = ref(null)

  const form = useValidatedForm({
    schema: computed(() => emailChangeSchema(t)),
    initialValues: { newEmail: '' },
    action: 'entities.emailChange.request',
    submit: async ({ newEmail }) => {
      const { data } = await entitiesService.requestEmailChange(newEmail.trim())
      target.value = newEmail.trim()
      otp.setChallenge(data.otp)
      step.value = EMAIL_CHANGE_STEPS.CODE
      if (data.message) toast.info(data.message)
    },
  })

  function codeProblem(entered) {
    if (otp.expired.value) return t('auth.otp.errors.expired')
    if (entered.length !== otp.length.value) return t('auth.otp.errors.incomplete')
    return ''
  }

  async function verify(value = code.value) {
    if (verifying.value) return
    codeError.value = codeProblem(value)
    if (codeError.value) return
    verifying.value = true
    logger.breadcrumb('form', VERIFY_ACTION, { fields: ['challengeId', 'code'] })
    try {
      const { data } = await entitiesService.confirmEmailChange({
        challengeId: otp.challengeId.value,
        code: value,
      })
      otp.stop()
      result.value = data
      step.value = EMAIL_CHANGE_STEPS.DONE
      onCompleted(data)
    } catch (error) {
      logger.error(error, { action: VERIFY_ACTION })
      codeError.value =
        error?.normalized?.fieldErrors?.code?.[0] ||
        error?.normalized?.message ||
        t('auth.otp.errors.invalid')
      code.value = ''
    } finally {
      verifying.value = false
    }
  }

  function reset() {
    otp.stop()
    step.value = EMAIL_CHANGE_STEPS.EMAIL
    form.resetForm({ values: { newEmail: '' } })
    code.value = ''
    codeError.value = ''
    result.value = null
  }

  return { form, otp, step, target, code, codeError, verifying, result, verify, reset }
}
