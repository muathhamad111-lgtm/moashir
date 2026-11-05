import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import Otp from '@/views/auth/Otp.vue'
import { authService } from '@/services/authService'
import { useAuthFlowStore } from '@/stores/authFlow'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/authService', () => ({
  UNAUTHENTICATED_STATUS: 401,
  authService: { verifyOtp: vi.fn(), resendOtp: vi.fn() },
}))

const NOW = new Date('2026-11-05T09:00:00Z')

function challenge(id = 'c-1', { resendInSeconds = 60, channel = 'email' } = {}) {
  return {
    challengeId: id,
    channel,
    length: 6,
    expiresAt: new Date(NOW.getTime() + 300 * 1000).toISOString(),
    resendAvailableAt: new Date(NOW.getTime() + resendInSeconds * 1000).toISOString(),
  }
}

function rejection(status, code, retryAfter) {
  return {
    response: { status, headers: retryAfter ? { 'retry-after': String(retryAfter) } : {} },
    normalized: { status, code, message: code },
  }
}

async function mountOtp({
  locale = 'en',
  officialEmail = 'info@alamal.example.sa',
  identifier = '',
  intent = 'login',
  current = challenge(),
  path = '/auth/otp',
} = {}) {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  if (current)
    useAuthFlowStore().setChallenge({ challenge: current, officialEmail, intent, identifier })
  await globals.router.push(path)
  const replace = vi.spyOn(globals.router, 'replace')
  const push = vi.spyOn(globals.router, 'push').mockResolvedValue(undefined)
  const wrapper = mount(Otp, { global: globals })
  await flushPromises()
  return { wrapper, replace, push }
}

function mountCode(identifier, channel = 'email', options = {}) {
  return mountOtp({
    intent: 'code',
    officialEmail: '',
    identifier,
    current: challenge('c-1', { channel }),
    ...options,
  })
}

async function typeCode(wrapper, code) {
  const boxes = wrapper.findAll('.otp-input__box')
  for (const [index, digit] of [...code].entries()) await boxes[index].setValue(digit)
  await flushPromises()
  await flushPromises()
}

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] })
  vi.setSystemTime(NOW)
  vi.clearAllMocks()
})

afterEach(() => {
  vi.useRealTimers()
})

describe('OTP step after the password', () => {
  it('names the masked official e-mail, the 5-minute validity and the resend countdown', async () => {
    const { wrapper } = await mountOtp()
    const target = wrapper.find('.auth-otp__target')
    expect(target.text()).toBe('in•••@alamal.example.sa')
    expect(target.attributes('dir')).toBe('ltr')
    expect(wrapper.text()).toContain('The code is valid for 5 minutes and works once.')
    expect(wrapper.text()).toContain('Code expires in 5:00')
    expect(wrapper.text()).toContain('Resend available in 60s')
    expect(wrapper.findAll('.otp-input__box')).toHaveLength(6)
    expect(wrapper.find('.auth-otp__tile').exists()).toBe(false)
    expect(wrapper.find('.auth-form__switch a').text()).toBe('Back to login')
  })

  it('speaks of the entity e-mail without an address after a mobile or unified-number sign-in', async () => {
    const { wrapper } = await mountOtp({ locale: 'ar', officialEmail: '' })
    expect(wrapper.find('.auth-otp__target').exists()).toBe(false)
    expect(wrapper.text()).toContain('البريد الإلكتروني الرسمي للجهة')
    expect(wrapper.text()).toContain('الرمز صالح لمدة 5 دقائق')
  })

  it('shows the full address while confirming a new registration', async () => {
    const { wrapper } = await mountOtp({ intent: 'register' })
    expect(wrapper.find('.auth-otp__target').text()).toBe('info@alamal.example.sa')
  })

  it('returns to sign in when no challenge is waiting', async () => {
    const { replace } = await mountOtp({ current: null })
    expect(replace).toHaveBeenCalledWith({ name: 'auth.login' })
  })

  it('verifies the code and continues to the workspace', async () => {
    authService.verifyOtp.mockResolvedValue({ data: { step: 'authenticated', user: { id: 1 } } })
    const { wrapper, push } = await mountOtp()
    await typeCode(wrapper, '482913')
    expect(authService.verifyOtp).toHaveBeenCalledWith({ challengeId: 'c-1', code: '482913' })
    expect(push).toHaveBeenCalledWith({ name: 'app.dashboard' })
  })

  it('keeps a wrong code inline and lets the user try again', async () => {
    authService.verifyOtp.mockRejectedValue(rejection(422, 'otp.invalid'))
    const { wrapper } = await mountOtp()
    await typeCode(wrapper, '000000')
    expect(wrapper.find('.auth-otp__error').text()).toBe('The verification code is incorrect.')
    expect(wrapper.find('.auth-failure').exists()).toBe(false)
    expect(wrapper.find('.otp-input__box').attributes('disabled')).toBeUndefined()
  })

  it('ends the step when the account gets locked and says how long to wait', async () => {
    authService.verifyOtp.mockRejectedValue(rejection(423, 'account_locked', 900))
    const { wrapper } = await mountOtp({ locale: 'ar' })
    await typeCode(wrapper, '000000')
    expect(wrapper.find('.auth-failure').text()).toBe(
      'أُقفل الحساب مؤقتًا بعد محاولات خاطئة متكررة. حاول مرة أخرى بعد 15 دقيقة.',
    )
    expect(wrapper.find('.auth-otp__resend').exists()).toBe(false)
    expect(wrapper.find('.otp-input__box').exists()).toBe(false)
    expect(wrapper.find('.auth-form__switch a').attributes('href')).toBe('/auth/login')
  })

  it('resends with the challenge only and explains the per-account cooldown', async () => {
    authService.resendOtp
      .mockResolvedValueOnce({ data: { otp: challenge('c-2', { resendInSeconds: 0 }) } })
      .mockRejectedValueOnce(rejection(429, 'otp.cooldown', 480))
    const { wrapper } = await mountOtp({ current: challenge('c-1', { resendInSeconds: 0 }) })
    await wrapper.find('.auth-otp__resend button').trigger('click')
    await flushPromises()
    expect(authService.resendOtp).toHaveBeenCalledWith({ challengeId: 'c-1' })
    expect(useAuthFlowStore().challenge.challengeId).toBe('c-2')
    await wrapper.find('.auth-otp__resend button').trigger('click')
    await flushPromises()
    expect(wrapper.find('.auth-failure').text()).toBe(
      'Too many codes were requested in a short time. Try again in 8 minutes.',
    )
    expect(wrapper.findAll('.otp-input__box')).toHaveLength(6)
  })
})

describe('OTP step of the code sign-in', () => {
  it.each([
    [
      'email',
      'Info@Alamal.example.sa',
      'إذا كان الحساب مسجّلًا، أرسلنا الرمز إلى البريد الذي أدخلته:',
      'in•••@alamal.example.sa',
      'envelope',
    ],
    [
      'sms',
      '055 123 4567',
      'إذا كان الحساب مسجّلًا، أرسلنا الرمز إلى الجوال الذي أدخلته:',
      '05•••••67',
      'send',
    ],
    [
      'sms',
      '+966551234567',
      'إذا كان الحساب مسجّلًا، أرسلنا الرمز إلى الجوال الذي أدخلته:',
      '05•••••67',
      'send',
    ],
    [
      'email',
      '0551234567',
      'إذا كان الحساب مسجّلًا، أرسلنا الرمز إلى البريد الرسمي المسجّل لهذا الحساب.',
      null,
      'envelope',
    ],
    [
      'email',
      '7001234567',
      'إذا كان الحساب مسجّلًا، أرسلنا الرمز إلى البريد الرسمي المسجّل لهذا الحساب.',
      null,
      'envelope',
    ],
    ['sms', '7001234567', 'إذا كان الحساب مسجّلًا، أرسلنا إليه رمز التحقق.', null, 'send'],
  ])(
    'channel %s with the typed %s shows only the user input',
    async (channel, identifier, subtitle, target, icon) => {
      const { wrapper } = await mountCode(identifier, channel, { locale: 'ar' })
      expect(wrapper.find('.auth-form__subtitle').text()).toBe(subtitle)
      const shown = wrapper.find('.auth-otp__target')
      expect(shown.exists() ? shown.text() : null).toBe(target)
      expect(
        wrapper.findAll('.auth-otp__target').every((node) => node.attributes('dir') === 'ltr'),
      ).toBe(true)
      expect(wrapper.find('.auth-otp__tile svg').attributes('data-icon')).toBe(icon)
      expect(wrapper.find('.auth-otp__tile').attributes('data-channel')).toBe(channel)
    },
  )

  it('offers the password instead, keeping the redirect target', async () => {
    const { wrapper } = await mountCode('7001234567', 'email', {
      path: '/auth/otp?redirect=%2Fassessment%2Fself',
    })
    const link = wrapper.find('.auth-otp__password')
    expect(link.text()).toBe('Sign in with your password instead')
    expect(link.attributes('href')).toBe('/auth/login?redirect=/assessment/self')
    expect(wrapper.text()).not.toContain('Back to login')
  })

  it('follows a channel change after a resend', async () => {
    authService.resendOtp.mockResolvedValueOnce({
      data: { otp: challenge('c-2', { channel: 'email' }) },
    })
    const { wrapper } = await mountOtp({
      intent: 'code',
      officialEmail: '',
      identifier: '0551234567',
      current: challenge('c-1', { resendInSeconds: 0, channel: 'sms' }),
    })
    expect(wrapper.find('.auth-otp__target').text()).toBe('05•••••67')
    await wrapper.find('.auth-otp__resend button').trigger('click')
    await flushPromises()
    expect(wrapper.find('.auth-otp__target').exists()).toBe(false)
    expect(wrapper.find('.auth-form__subtitle').text()).toBe(
      'If the account is registered, we sent the code to the official e-mail registered for this account.',
    )
  })

  it('clears a wrong code and keeps the challenge open', async () => {
    authService.verifyOtp.mockRejectedValue(rejection(422, 'otp.invalid'))
    const { wrapper } = await mountCode('7001234567')
    await typeCode(wrapper, '000000')
    expect(wrapper.find('.auth-otp__error').attributes('role')).toBe('alert')
    expect(wrapper.findAll('.otp-input__box').every((box) => box.element.value === '')).toBe(true)
    expect(wrapper.find('.otp-input__box').classes()).toContain('is-invalid')
  })

  it('disables the verification of an expired code and keeps the resend', async () => {
    const { wrapper } = await mountCode('7001234567', 'email', {
      current: { ...challenge('c-1', { resendInSeconds: 0 }), expiresAt: NOW.toISOString() },
    })
    expect(wrapper.find('.auth-otp__expiry').classes()).toContain('is-expired')
    expect(wrapper.find('.auth-form__actions button').attributes('disabled')).toBeDefined()
    expect(wrapper.find('.auth-otp__resend button').exists()).toBe(true)
  })

  it.each([
    [
      'otp.attempts_exceeded',
      423,
      'Too many attempts',
      'This code has ended. Request a new code to continue.',
    ],
    ['otp.challenge_invalid', 422, 'This code has ended', 'Request a new code to continue.'],
  ])('ends the challenge on %s and asks for a new code', async (code, status, title, body) => {
    authService.verifyOtp.mockRejectedValue(rejection(status, code))
    const { wrapper } = await mountCode('7001234567', 'email', {
      path: '/auth/otp?redirect=%2Fassessment%2Fself',
    })
    await typeCode(wrapper, '000000')
    const alert = wrapper.find('.auth-failure')
    expect(alert.attributes('role')).toBe('alert')
    expect(alert.find('.auth-status__title').text()).toBe(title)
    expect(alert.find('.auth-status__text').text()).toBe(body)
    expect(wrapper.find('.otp-input__box').exists()).toBe(false)
    expect(wrapper.find('.auth-otp__expiry').exists()).toBe(false)
    expect(wrapper.find('.auth-otp__resend').exists()).toBe(false)
    expect(wrapper.find('.auth-otp__request-new').attributes('href')).toBe(
      '/auth/login?redirect=/assessment/self&method=code',
    )
    expect(wrapper.find('.auth-otp__password').exists()).toBe(true)
  })

  it('ends the challenge of a locked account with the wait and no new-code action', async () => {
    authService.verifyOtp.mockRejectedValue(rejection(423, 'account_locked', 900))
    const { wrapper } = await mountCode('7001234567', 'email', { locale: 'ar' })
    await typeCode(wrapper, '000000')
    const alert = wrapper.find('.auth-failure')
    expect(alert.attributes('data-failure')).toBe('locked')
    expect(alert.find('.auth-status__title').text()).toBe('الحساب مقفل مؤقتًا')
    expect(alert.find('.auth-status__text').text()).toBe('حاول مرة أخرى بعد 15 دقيقة.')
    expect(wrapper.find('.auth-otp__request-new').exists()).toBe(false)
    expect(wrapper.find('.otp-input__box').exists()).toBe(false)
    expect(wrapper.find('.auth-otp__password').text()).toBe('الدخول بكلمة المرور بدلًا من ذلك')
  })

  it('says to try later when the lock has no announced wait', async () => {
    authService.verifyOtp.mockRejectedValue(rejection(423, 'account_locked'))
    const { wrapper } = await mountCode('7001234567')
    await typeCode(wrapper, '000000')
    expect(wrapper.find('.auth-failure .auth-status__text').text()).toBe('Try again later.')
  })

  it('keeps the code usable after too many resend requests', async () => {
    authService.resendOtp.mockRejectedValue(rejection(429, 'rate_limited', 540))
    const { wrapper } = await mountCode('7001234567', 'email', {
      current: challenge('c-1', { resendInSeconds: 0 }),
    })
    await wrapper.find('.auth-otp__resend button').trigger('click')
    await flushPromises()
    expect(wrapper.find('.auth-failure').attributes('data-failure')).toBe('cooldown')
    expect(wrapper.findAll('.otp-input__box')).toHaveLength(6)
    expect(wrapper.find('.auth-otp__request-new').exists()).toBe(false)
  })
})
