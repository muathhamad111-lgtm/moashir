import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useAuthFlowStore } from '@/stores/authFlow'
import { useOtpChallenge } from '@/composables/useOtpChallenge'
import { useLoginOutcome } from '@/composables/useLoginOutcome'
import { useToast } from '@/composables/useToast'
import { logger } from '@/utils/logger'
import { codeFailureOf, endsTheChallenge, failureText } from '@/utils/auth/authFailures'
import { maskEmail } from '@/utils/auth/signInIdentifier'
import { AUTH_FLOW_INTENTS } from '@/constants/loginSteps'
import { CODE_FAILURES } from '@/constants/signIn'

const INLINE_FAILURES = Object.freeze([CODE_FAILURES.INVALID, CODE_FAILURES.EXPIRED])

export function useOtpStep() {
  const { t } = useI18n()
  const auth = useAuthStore()
  const authFlow = useAuthFlowStore()
  const toast = useToast()
  const { continueAfter } = useLoginOutcome()
  const otp = useOtpChallenge()
  const code = ref('')
  const codeError = ref('')
  const failure = ref(null)
  const submitting = ref(false)
  const resending = ref(false)

  const isRegistration = computed(() => authFlow.intent === AUTH_FLOW_INTENTS.REGISTER)
  const target = computed(() => {
    if (isRegistration.value) return authFlow.officialEmail
    return maskEmail(authFlow.officialEmail)
  })
  const ended = computed(() => Boolean(failure.value) && endsTheChallenge(failure.value))
  const locked = computed(() => submitting.value || otp.expired.value || ended.value)

  function reject(error) {
    const next = codeFailureOf(error)
    logger.breadcrumb('auth', 'auth.otp.rejected', { failure: next.kind })
    code.value = ''
    if (INLINE_FAILURES.includes(next.kind)) {
      codeError.value = failureText(t, 'code', next)
      return
    }
    failure.value = next
  }

  function start() {
    if (!authFlow.hasChallenge) return false
    otp.setChallenge(authFlow.challenge)
    return true
  }

  async function verify(value = code.value) {
    if (locked.value) return
    if (value.length !== otp.length.value) {
      codeError.value = t('auth.otp.errors.incomplete')
      return
    }
    submitting.value = true
    codeError.value = ''
    failure.value = null
    logger.breadcrumb('form', 'auth.otp.verify', { fields: ['challengeId', 'code'] })
    try {
      const outcome = await auth.verifyOtp({ challengeId: otp.challengeId.value, code: value })
      otp.stop()
      await continueAfter(outcome)
    } catch (error) {
      reject(error)
    } finally {
      submitting.value = false
    }
  }

  async function resend() {
    if (resending.value || !otp.canResend.value || ended.value) return
    resending.value = true
    codeError.value = ''
    failure.value = null
    try {
      const data = await auth.resendOtp({ challengeId: otp.challengeId.value })
      authFlow.setChallenge({ challenge: data.otp })
      otp.setChallenge(data.otp)
      code.value = ''
      toast.success(t('auth.otp.resent'))
    } catch (error) {
      reject(error)
    } finally {
      resending.value = false
    }
  }

  return {
    otp,
    code,
    codeError,
    failure,
    submitting,
    resending,
    isRegistration,
    target,
    ended,
    locked,
    start,
    verify,
    resend,
  }
}
