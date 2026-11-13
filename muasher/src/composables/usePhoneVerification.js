import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useOtpChallenge } from '@/composables/useOtpChallenge'
import { useToast } from '@/composables/useToast'
import { phoneVerificationService } from '@/services/phoneVerificationService'
import { logger } from '@/utils/logger'
import { codeFailureOf, failureText } from '@/utils/auth/authFailures'

const CODE_ERROR_PREFIX = 'otp.'
const PHASES = Object.freeze({ IDLE: 'idle', CODE: 'code' })

export function usePhoneVerification({ onVerified }) {
  const { t } = useI18n()
  const toast = useToast()
  const otp = useOtpChallenge()
  const phase = ref(PHASES.IDLE)
  const busy = ref(false)
  const code = ref('')
  const errorText = ref('')

  function textOf(error) {
    const errorCode = String(error?.normalized?.code ?? '')
    if (errorCode.startsWith(CODE_ERROR_PREFIX))
      return failureText(t, 'phone', codeFailureOf(error))
    return error?.normalized?.message || t('errors.unexpected')
  }

  async function run(action, work) {
    if (busy.value) return
    busy.value = true
    errorText.value = ''
    logger.breadcrumb('form', `entities.phone.${action}`, { action })
    try {
      await work()
    } catch (error) {
      errorText.value = textOf(error)
      code.value = ''
    } finally {
      busy.value = false
    }
  }

  function send() {
    return run('send', async () => {
      const { data } = await phoneVerificationService.start()
      otp.setChallenge(data)
      code.value = ''
      phase.value = PHASES.CODE
    })
  }

  function confirm(value = code.value) {
    if (value.length !== otp.length.value) {
      errorText.value = t('auth.otp.errors.incomplete')
      return Promise.resolve()
    }
    return run('confirm', async () => {
      const { data } = await phoneVerificationService.confirm({
        challengeId: otp.challengeId.value,
        code: value,
      })
      otp.stop()
      phase.value = PHASES.IDLE
      toast.success(t('entities.phone.verifiedToast'))
      onVerified(data)
    })
  }

  function cancel() {
    otp.stop()
    phase.value = PHASES.IDLE
    code.value = ''
    errorText.value = ''
  }

  return { PHASES, otp, phase, busy, code, errorText, send, confirm, cancel }
}
