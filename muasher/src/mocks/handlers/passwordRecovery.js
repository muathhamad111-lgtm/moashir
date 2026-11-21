import {
  maskNationalId,
  nationalIdOf,
  openChange,
  replaceAtAcceptance,
} from '../stores/identityStore'
import { identityMessages } from '../fixtures/identityMessages'
import { nationalIdProblem, normalizeNationalId } from '@/utils/identity/nationalId'
import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse, noContent } from '../contract/responses'
import { requestLocale } from '../contract/locale'
import { MOCK_PASSWORD, PASSWORD_RULES, RECOVERY_TOKENS } from '../fixtures/auth'
import { isResetLinkUsable, markResetLinkUsed } from '../stores/passwordResetLinks'
import { currentTerms } from '../stores/termsStore'
import { findStaffUser } from '../stores/staffUsers'
import { readJson } from '../stores/entityRejection'
import { currentSessionUser } from '../stores/sessionStore'

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const LATENCY_MS = 200

const MESSAGES = {
  linkSent: {
    ar: 'إن كان البريد مسجلًا لدينا فسنرسل إليه رابطًا لإعادة تعيين كلمة المرور.',
    en: 'If the e-mail is registered, we will send a link to reset the password.',
  },
  reset: { ar: 'تم تعيين كلمة المرور الجديدة.', en: 'The new password was set.' },
  tokenInvalid: {
    ar: 'رابط إعادة التعيين غير صالح أو انتهت صلاحيته أو استُخدم من قبل.',
    en: 'This password reset link is invalid, has expired or was already used.',
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
  currentPassword: {
    ar: 'كلمة المرور الحالية غير صحيحة.',
    en: 'The current password is incorrect.',
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

// Blank keeps the administrator's entry; a different number replaces it at once; a first number waits for review.
function recordInviteeNationalId(invitee, value) {
  if (nationalIdOf(invitee.id)) return replaceAtAcceptance(invitee, value)
  return openChange({
    user: invitee,
    submitter: invitee,
    newNationalId: value,
    kind: 'first_entry',
  })
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

function tokenInvalid(request) {
  return errorResponse(422, 'token.invalid', say(request, 'tokenInvalid'))
}

function validationFailure(request, fields) {
  return errorResponse(422, 'validation_failed', Object.values(fields)[0][0], fields)
}

// Mirrors ChangePasswordRequest: the current password first, then the same policy as a reset.
function changeErrors(request, body) {
  if (body.currentPassword !== MOCK_PASSWORD)
    return { currentPassword: [say(request, 'currentPassword')] }
  return passwordErrors(request, body, { withHistory: true })
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

    http.post('/api/v1/auth/password/reset/check', async ({ request }) => {
      await delay(LATENCY_MS)
      const body = await readJson(request)
      if (!isResetLinkUsable(body.token)) return tokenInvalid(request)
      return envelope({ usable: true })
    }),

    // The account comes from the link alone, as in the API; an e-mail in the body is ignored.
    http.post('/api/v1/auth/password/reset', async ({ request }) => {
      await delay(LATENCY_MS)
      const body = await readJson(request)
      const fields = passwordErrors(request, body, { withHistory: true })
      if (Object.keys(fields).length) return validationFailure(request, fields)
      if (!isResetLinkUsable(body.token)) return tokenInvalid(request)
      markResetLinkUsed(body.token)
      return envelope({ message: say(request, 'reset') })
    }),

    http.post('/api/v1/auth/password/change', async ({ request }) => {
      await delay(LATENCY_MS)
      if (!currentSessionUser()) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
      const fields = changeErrors(request, await readJson(request))
      if (Object.keys(fields).length) return validationFailure(request, fields)
      return noContent()
    }),

    http.get('/api/v1/auth/invitations/:token', async ({ params, request }) => {
      await delay(LATENCY_MS)
      const found = invitationUser(params.token)
      if (!found.user) return errorResponse(found.status, found.code, say(request, found.key))
      return envelope({
        usable: true,
        nationalIdMasked: maskNationalId(nationalIdOf(found.user.id)),
      })
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
      const problem = body.nationalId ? nationalIdProblem(body.nationalId) : null
      if (problem)
        fields.nationalId = [
          identityMessages[`identity.national_id.${problem}`][requestLocale(request)],
        ]
      if (Object.keys(fields).length) return validationFailure(request, fields)
      if (body.nationalId) recordInviteeNationalId(found.user, normalizeNationalId(body.nationalId))
      found.user.status = 'active'
      return continueSignIn({ kind: 'staff', record: { ...found.user, mfaEnrolled: false } })
    }),
  ]
}
