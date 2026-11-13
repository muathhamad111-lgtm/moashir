import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { requestLocale } from '../contract/locale'
import { MOCK_PASSWORD, PASSWORD_RULES, RECOVERY_TOKENS } from '../fixtures/auth'
import { findAccountByEmail } from '../stores/accounts'
import { currentTerms } from '../stores/termsStore'
import { findStaffUser } from '../stores/staffUsers'
import { readJson } from '../stores/entityRejection'

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const LATENCY_MS = 200

const MESSAGES = {
  linkSent: {
    ar: 'إن كان البريد مسجلًا لدينا فسنرسل إليه رابطًا لإعادة تعيين كلمة المرور.',
    en: 'If the e-mail is registered, we will send a link to reset the password.',
  },
  reset: { ar: 'تم تعيين كلمة المرور الجديدة.', en: 'The new password was set.' },
  tokenInvalid: {
    ar: 'رابط إعادة التعيين غير صالح أو منتهي الصلاحية.',
    en: 'This password reset link is invalid or has expired.',
  },
  email: { ar: 'أدخل بريدًا إلكترونيًا صحيحًا.', en: 'Enter a valid e-mail address.' },
  min: {
    ar: `يجب ألا تقل كلمة المرور عن ${PASSWORD_RULES.MIN} حرفًا.`,
    en: `The password must be at least ${PASSWORD_RULES.MIN} characters.`,
  },
  confirmed: {
    ar: 'تأكيد كلمة المرور غير مطابق.',
    en: 'The password confirmation does not match.',
  },
  history: {
    ar: 'لا يمكن استخدام إحدى كلمات المرور الخمس الأخيرة.',
    en: 'The password cannot be one of your last five passwords.',
  },
  terms: {
    ar: 'وافق على الإصدار الحالي من الشروط والأحكام.',
    en: 'Accept the current version of the terms and conditions.',
  },
  invitationExpired: {
    ar: 'انتهت صلاحية هذه الدعوة.',
    en: 'This invitation has expired.',
  },
  invitationInvalid: {
    ar: 'رابط الدعوة غير صالح أو استُخدم من قبل.',
    en: 'This invitation link is invalid or was already used.',
  },
}

function say(request, key) {
  return MESSAGES[key][requestLocale(request)] ?? MESSAGES[key].ar
}

function passwordErrors(request, body, { withHistory }) {
  const password = typeof body.password === 'string' ? body.password : ''
  if (password.length < PASSWORD_RULES.MIN || password.length > PASSWORD_RULES.MAX)
    return { password: [say(request, 'min')] }
  if (password !== body.passwordConfirmation) return { password: [say(request, 'confirmed')] }
  if (withHistory && password === MOCK_PASSWORD) return { password: [say(request, 'history')] }
  return {}
}

function validationFailure(request, fields) {
  return errorResponse(422, 'validation_failed', Object.values(fields)[0][0], fields)
}

function invitationUser(token) {
  const target = RECOVERY_TOKENS.INVITATIONS[token]
  if (target === 'expired')
    return { status: 410, code: 'invitation.expired', key: 'invitationExpired' }
  const user = typeof target === 'number' ? findStaffUser(target) : null
  if (!user || user.status !== 'invited')
    return { status: 404, code: 'invitation.invalid', key: 'invitationInvalid' }
  return { user }
}

function termsErrors(request, body) {
  const terms = currentTerms().terms
  if (terms && body.termsVersionId !== terms.id) return { termsVersionId: [say(request, 'terms')] }
  return {}
}

export function passwordRecoveryHandlers({ continueSignIn }) {
  return [
    http.post('/api/v1/auth/password/forgot', async ({ request }) => {
      await delay(LATENCY_MS)
      const body = await readJson(request)
      if (!EMAIL.test(String(body.email ?? '').trim()))
        return validationFailure(request, { email: [say(request, 'email')] })
      return envelope({ message: say(request, 'linkSent') })
    }),

    http.post('/api/v1/auth/password/reset', async ({ request }) => {
      await delay(LATENCY_MS)
      const body = await readJson(request)
      const fields = passwordErrors(request, body, { withHistory: true })
      if (!EMAIL.test(String(body.email ?? '').trim())) fields.email = [say(request, 'email')]
      if (Object.keys(fields).length) return validationFailure(request, fields)
      const account = findAccountByEmail(String(body.email).trim())
      if (!account || body.token === RECOVERY_TOKENS.EXPIRED_RESET)
        return errorResponse(422, 'token.invalid', say(request, 'tokenInvalid'))
      return envelope({ message: say(request, 'reset') })
    }),

    http.post('/api/v1/auth/invitations/:token/accept', async ({ params, request }) => {
      await delay(LATENCY_MS)
      const found = invitationUser(params.token)
      if (!found.user) return errorResponse(found.status, found.code, say(request, found.key))
      const body = await readJson(request)
      const fields = {
        ...passwordErrors(request, body, { withHistory: false }),
        ...termsErrors(request, body),
      }
      if (Object.keys(fields).length) return validationFailure(request, fields)
      found.user.status = 'active'
      return continueSignIn({ kind: 'staff', record: { ...found.user, mfaEnrolled: false } })
    }),
  ]
}
