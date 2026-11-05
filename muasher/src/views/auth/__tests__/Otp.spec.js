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

function challenge(id = 'c-1', { resendInSeconds = 60 } = {}) {
  return {
    challengeId: id,
    channel: 'email',
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
  intent = 'login',
  current = challenge(),
} = {}) {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  if (current) useAuthFlowStore().setChallenge({ challenge: current, officialEmail, intent })
  await globals.router.push('/auth/otp')
  const replace = vi.spyOn(globals.router, 'replace')
  const push = vi.spyOn(globals.router, 'push').mockResolvedValue(undefined)
  const wrapper = mount(Otp, { global: globals })
  await flushPromises()
  return { wrapper, replace, push }
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

describe('OTP step', () => {
  it('names the masked official e-mail, the 5-minute validity and the resend countdown', async () => {
    const { wrapper } = await mountOtp()
    const target = wrapper.find('.auth-otp__target')
    expect(target.text()).toBe('in***@alamal.example.sa')
    expect(target.attributes('dir')).toBe('ltr')
    expect(wrapper.text()).toContain('The code is valid for 5 minutes and works once.')
    expect(wrapper.text()).toContain('Code expires in 5:00')
    expect(wrapper.text()).toContain('Resend available in 60s')
    expect(wrapper.findAll('.otp-input__box')).toHaveLength(6)
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
    expect(wrapper.find('.otp-input__box').attributes('disabled')).toBeDefined()
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
  })
})
