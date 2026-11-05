import { describe, expect, it } from 'vitest'
import { createI18n } from 'vue-i18n'
import ar from '@/i18n/locales/ar.json'
import en from '@/i18n/locales/en.json'
import { PLURAL_RULES } from '@/utils/arabicPlural'
import {
  identifierForRequest,
  identifierTypeOf,
  maskEmail,
  maskMobile,
} from '@/utils/auth/signInIdentifier'
import {
  identifierHintOf,
  signInCodeRequestOf,
  signInCodeSchema,
  signInEmailOf,
  signInSchema,
} from '@/utils/auth/signInForm'
import { channelIconOf, otpDestinationOf } from '@/utils/auth/otpDestination'
import { normalizedCodeChannels } from '@/utils/auth/codeChannels'
import {
  codeFailureOf,
  codeRequestFailureOf,
  endsTheChallenge,
  failureText,
  signInFailureOf,
} from '@/utils/auth/authFailures'

function i18nOf(locale) {
  return createI18n({ legacy: false, locale, messages: { ar, en }, pluralRules: PLURAL_RULES })
    .global
}

function rejection(status, code, retryAfter) {
  return {
    response: { status, headers: retryAfter ? { 'retry-after': String(retryAfter) } : {} },
    normalized: { status, code },
  }
}

describe('sign-in identifier', () => {
  it.each([
    ['Info@Alamal.example.sa', 'email', 'info@alamal.example.sa'],
    ['0551234567', 'mobile', '0551234567'],
    ['055-123 4567', 'mobile', '0551234567'],
    ['+966551234567', 'mobile', '+966551234567'],
    ['00966551234567', 'mobile', '00966551234567'],
    ['551234567', 'mobile', '551234567'],
    ['7001234567', 'unified_number', '7001234567'],
    ['٧٠٠١٢٣٤٥٦٧', 'unified_number', '7001234567'],
  ])('recognises %s as %s like the backend', (typed, type, sent) => {
    expect(identifierTypeOf(typed)).toBe(type)
    expect(identifierForRequest(typed)).toBe(sent)
  })

  it.each(['', 'info@', '12345', '6001234567', '0451234567', '70012345678', 'name'])(
    'refuses %s',
    (typed) => expect(identifierTypeOf(typed)).toBeNull(),
  )

  it('masks the e-mail and keeps only an e-mail identifier for the code step', () => {
    expect(maskEmail('info@alamal.example.sa')).toBe('in•••@alamal.example.sa')
    expect(maskEmail('a@b.sa')).toBe('a•••@b.sa')
    expect(maskEmail('')).toBe('')
    expect(signInEmailOf('info@alamal.example.sa')).toBe('info@alamal.example.sa')
    expect(signInEmailOf('0551234567')).toBe('')
  })

  it('validates the form and lists the accepted identifiers in the reading language', async () => {
    const t = i18nOf('en').t
    const schema = signInSchema(t)
    await expect(schema.validate({ identifier: '7001234567', password: 'x' })).resolves.toBeTruthy()
    await expect(schema.validate({ identifier: 'abc', password: 'x' })).rejects.toThrow(
      'unified national number',
    )
    await expect(schema.validate({ identifier: '7001234567', password: '' })).rejects.toThrow(
      'This field is required',
    )
    expect(identifierHintOf(t, 'en', ['email', 'mobile'])).toBe(
      'Use your official e-mail or your verified mobile number (05XXXXXXXX).',
    )
    const arabic = i18nOf('ar').t
    expect(identifierHintOf(arabic, 'ar', ['email', 'unified_number'])).toBe(
      'استخدم البريد الإلكتروني الرسمي أو الرقم الوطني الموحد للجهة (10 أرقام تبدأ بالرقم 7).',
    )
  })
})

describe('sign-in code request', () => {
  it.each([
    ['0551234567', '05•••••67'],
    ['055-123 4567', '05•••••67'],
    ['+966551234567', '05•••••67'],
    ['00966551234567', '05•••••67'],
    ['551234567', '05•••••67'],
    ['٠٥٥١٢٣٤٥٦٧', '05•••••67'],
  ])('masks the typed mobile %s as %s', (typed, masked) => {
    expect(maskMobile(typed)).toBe(masked)
  })

  it('masks nothing that is not a mobile', () => {
    expect(maskMobile('7001234567')).toBe('')
    expect(maskMobile('info@alamal.example.sa')).toBe('')
    expect(maskMobile('')).toBe('')
  })

  it('validates the identifier alone and sends it in the backend form', async () => {
    const t = i18nOf('en').t
    const schema = signInCodeSchema(t)
    await expect(schema.validate({ identifier: '0551234567' })).resolves.toBeTruthy()
    await expect(schema.validate({ identifier: '12345' })).rejects.toThrow(
      'unified national number',
    )
    await expect(schema.validate({ identifier: '' })).rejects.toThrow('This field is required')
    expect(signInCodeRequestOf({ identifier: ' Info@Alamal.example.sa ' })).toEqual({
      identifier: 'info@alamal.example.sa',
    })
    expect(signInCodeRequestOf({ identifier: '055 123 4567' })).toEqual({
      identifier: '0551234567',
    })
  })

  it('always keeps the e-mail channel first and drops unknown channels', () => {
    expect(normalizedCodeChannels(['sms', 'email'])).toEqual(['email', 'sms'])
    expect(normalizedCodeChannels(['sms'])).toEqual(['email', 'sms'])
    expect(normalizedCodeChannels(['fax'])).toEqual(['email'])
    expect(normalizedCodeChannels(undefined)).toEqual(['email'])
  })

  it.each([
    [
      'code',
      'email',
      'Info@Alamal.example.sa',
      'auth.otp.code.subtitleTypedEmail',
      'in•••@alamal.example.sa',
    ],
    ['code', 'sms', '0551234567', 'auth.otp.code.subtitleTypedMobile', '05•••••67'],
    ['code', 'email', '0551234567', 'auth.otp.code.subtitleRegisteredEmail', ''],
    ['code', 'email', '7001234567', 'auth.otp.code.subtitleRegisteredEmail', ''],
    ['code', 'sms', 'info@alamal.example.sa', 'auth.otp.code.subtitleGeneric', ''],
    ['login', 'email', '7001234567', 'auth.otp.signInSubtitleGeneric', ''],
  ])(
    'builds the %s subtitle for %s with %s from the user input only',
    (intent, channel, identifier, subtitleKey, target) => {
      const destination = otpDestinationOf({ intent, channel, identifier, officialEmail: '' })
      expect(destination).toMatchObject({ subtitleKey, target })
    },
  )

  it('names the code step channel icons', () => {
    expect(channelIconOf('sms')).toBe('send')
    expect(channelIconOf('email')).toBe('envelope')
    expect(channelIconOf(undefined)).toBe('envelope')
  })

  it.each([
    [rejection(429, 'rate_limited', 540), 'throttled', 9],
    [rejection(429, 'otp.cooldown'), 'throttled', null],
    [rejection(500, 'server_error'), 'unexpected', null],
    [rejection(422, 'credentials.invalid'), 'unexpected', null],
  ])('maps a code request rejection', (error, kind, minutes) => {
    expect(codeRequestFailureOf(error)).toEqual({ kind, minutes })
  })

  it('words the code request failures in both languages', () => {
    expect(failureText(i18nOf('ar').t, 'codeRequest', { kind: 'throttled', minutes: 9 })).toBe(
      'طلبات كثيرة. حاول مرة أخرى بعد 9 دقائق.',
    )
    expect(failureText(i18nOf('en').t, 'codeRequest', { kind: 'throttled', minutes: 1 })).toBe(
      'Too many requests. Try again in 1 minute.',
    )
  })
})

describe('authentication failures', () => {
  it.each([
    [rejection(422, 'credentials.invalid'), 'credentials', null],
    [rejection(423, 'account_locked', 900), 'locked', 15],
    [rejection(403, 'account_deactivated'), 'deactivated', null],
    [rejection(429, 'rate_limited', 30), 'throttled', 1],
    [rejection(429, 'otp.cooldown', 600), 'throttled', 10],
    [rejection(429, 'something_else'), 'throttled', null],
    [rejection(500, 'server_error'), 'unexpected', null],
  ])('maps a sign-in rejection', (error, kind, minutes) => {
    expect(signInFailureOf(error)).toEqual({ kind, minutes })
  })

  it.each([
    [rejection(422, 'otp.invalid'), 'invalid', false],
    [rejection(422, 'otp.expired'), 'expired', false],
    [rejection(422, 'otp.challenge_invalid'), 'challenge', true],
    [rejection(423, 'otp.attempts_exceeded'), 'attempts', true],
    [rejection(423, 'account_locked', 60), 'locked', true],
    [rejection(429, 'otp.cooldown', 120), 'cooldown', false],
    [rejection(503, 'service_unavailable'), 'unexpected', false],
  ])('maps a code rejection', (error, kind, ends) => {
    const failure = codeFailureOf(error)
    expect(failure.kind).toBe(kind)
    expect(endsTheChallenge(failure)).toBe(ends)
  })

  it('adds the wait with the Arabic plural forms', () => {
    const t = i18nOf('ar').t
    expect(failureText(t, 'code', { kind: 'cooldown', minutes: 2 })).toBe(
      'طُلبت رموز كثيرة خلال وقت قصير. حاول مرة أخرى بعد دقيقتين.',
    )
    expect(failureText(t, 'signIn', { kind: 'locked', minutes: 5 })).toBe(
      'أُقفل الحساب مؤقتًا بعد محاولات خاطئة متكررة. حاول مرة أخرى بعد 5 دقائق.',
    )
    expect(failureText(i18nOf('en').t, 'signIn', { kind: 'credentials', minutes: null })).toBe(
      'The sign-in details are incorrect. Check them and try again.',
    )
  })
})
