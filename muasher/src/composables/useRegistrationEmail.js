import { computed, ref, watch } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import { string } from 'yup'
import { authService } from '@/services/authService'
import { useOtpChallenge } from '@/composables/useOtpChallenge'
import { codeFailureOf, failureText } from '@/utils/auth/authFailures'
import { logger } from '@/utils/logger'

export const EMAIL_CHECK_STATES = Object.freeze({
  IDLE: 'idle',
  SENT: 'sent',
  VERIFIED: 'verified',
})

const EMAIL_FORMAT = string().trim().required().email()

/**
 * Proves the official e-mail inside the registration form: a code is e-mailed, the user types it,
 * and the token returned by the server travels with the registration. Editing the e-mail
 * afterwards drops the proof, so a different address must be checked again.
 */
export function useRegistrationEmail() {
  const { t } = useI18n()
  const {
    value: email,
    errorMessage: emailError,
    handleBlur: blurEmail,
    validate: validateEmail,
  } = useField('officialEmail')
  const { value: token, errorMessage: tokenError } = useField('emailVerificationToken')
  const otp = useOtpChallenge()
  const state = ref(EMAIL_CHECK_STATES.IDLE)
  const code = ref('')
  const error = ref('')
  const busy = ref(false)
  let checkedEmail = ''

  const normalizedEmail = computed(() =>
    String(email.value ?? '')
      .trim()
      .toLowerCase(),
  )

  watch(normalizedEmail, (next) => {
    if (state.value === EMAIL_CHECK_STATES.IDLE || next === checkedEmail) return
    reset()
  })

  function reset() {
    otp.stop()
    state.value = EMAIL_CHECK_STATES.IDLE
    token.value = ''
    code.value = ''
    error.value = ''
  }

  function fail(caught) {
    const failure = codeFailureOf(caught)
    logger.breadcrumb('auth', 'auth.registrationEmail.rejected', { failure: failure.kind })
    error.value = failureText(t, 'code', failure)
    code.value = ''
  }

  async function run(task) {
    if (busy.value) return
    busy.value = true
    error.value = ''
    try {
      await task()
    } catch (caught) {
      fail(caught)
    } finally {
      busy.value = false
    }
  }

  function startChallenge(data) {
    otp.setChallenge(data)
    checkedEmail = normalizedEmail.value
    state.value = EMAIL_CHECK_STATES.SENT
  }

  async function send() {
    const { valid } = await validateEmail()
    if (!valid || !EMAIL_FORMAT.isValidSync(email.value)) return
    await run(async () => {
      const { data } = await authService.requestRegistrationEmailCode({
        officialEmail: String(email.value).trim(),
      })
      startChallenge(data)
    })
  }

  async function resend() {
    if (!otp.canResend.value) return
    await run(async () => {
      const { data } = await authService.resendRegistrationEmailCode({
        challengeId: otp.challengeId.value,
      })
      startChallenge(data)
    })
  }

  async function verify(value = code.value) {
    if (value.length !== otp.length.value) {
      error.value = t('auth.otp.errors.incomplete')
      return
    }
    await run(async () => {
      const { data } = await authService.verifyRegistrationEmailCode({
        challengeId: otp.challengeId.value,
        code: value,
      })
      token.value = data.emailVerificationToken
      otp.stop()
      state.value = EMAIL_CHECK_STATES.VERIFIED
    })
  }

  return {
    email,
    emailError,
    blurEmail,
    state,
    code,
    error,
    busy,
    otp,
    tokenError,
    send,
    resend,
    verify,
    reset,
  }
}
