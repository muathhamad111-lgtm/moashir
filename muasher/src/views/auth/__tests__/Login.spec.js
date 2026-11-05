import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import Login from '@/views/auth/Login.vue'
import { authService } from '@/services/authService'
import { useAuthFlowStore } from '@/stores/authFlow'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/authService', () => ({
  UNAUTHENTICATED_STATUS: 401,
  authService: {
    getLoginModes: vi.fn(),
    signIn: vi.fn(),
  },
}))

const MODES = {
  identifiers: ['email', 'mobile', 'unified_number'],
  entitySecondFactorEnabled: true,
  otpLength: 6,
  otpExpirySeconds: 300,
  resendCooldownSeconds: 60,
}

function rejection(status, code, headers = {}) {
  return { response: { status, headers }, normalized: { status, code, message: code } }
}

async function mountLogin(locale = 'en', path = '/auth/login') {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  await globals.router.push(path)
  const push = vi.spyOn(globals.router, 'push')
  const wrapper = mount(Login, { global: globals })
  await flushPromises()
  return { wrapper, push }
}

async function settle() {
  for (let round = 0; round < 3; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

async function submit(wrapper, identifier, password = 'Wafa-Charity-Account-2026') {
  await wrapper.find('[name="identifier"]').setValue(identifier)
  await wrapper.find('[name="password"]').setValue(password)
  await wrapper.find('form').trigger('submit')
  await settle()
}

beforeEach(() => {
  vi.clearAllMocks()
  authService.getLoginModes.mockResolvedValue({ data: MODES })
})

describe('Login view', () => {
  it('shows one form with the identifier, the password and the forgotten-password link', async () => {
    const { wrapper } = await mountLogin()
    expect(wrapper.find('[role="tablist"]').exists()).toBe(false)
    expect(wrapper.find('[name="identifier"]').attributes()).toMatchObject({
      autocomplete: 'username',
      dir: 'ltr',
    })
    expect(wrapper.find('label[for]').text()).toBe('E-mail, mobile or unified national number')
    expect(wrapper.find('[name="password"]').attributes('type')).toBe('password')
    expect(wrapper.text()).toContain(
      "Use your official e-mail, your verified mobile number (05XXXXXXXX), or your entity's unified national number (10 digits starting with 7).",
    )
    const link = wrapper.find('.auth-form__forgot')
    expect(link.attributes('href')).toBe('/auth/forgot-password')
  })

  it('lists only the identifiers the server accepts in the hint', async () => {
    authService.getLoginModes.mockResolvedValue({ data: { ...MODES, identifiers: ['email'] } })
    const { wrapper } = await mountLogin()
    expect(wrapper.text()).toContain('Use your official e-mail.')
  })

  it('keeps the form usable when the login modes cannot be loaded', async () => {
    authService.getLoginModes.mockRejectedValue(new Error('offline'))
    const { wrapper } = await mountLogin('ar')
    expect(wrapper.find('form').exists()).toBe(true)
    expect(wrapper.text()).toContain('الرقم الوطني الموحد للجهة')
  })

  it.each([
    ['info@alamal.example.sa', 'info@alamal.example.sa'],
    ['055 123 4567', '0551234567'],
    ['٧٠٠١٢٣٤٥٦٧', '7001234567'],
  ])('sends %s as the identifier %s with the password', async (typed, sent) => {
    authService.signIn.mockResolvedValue({ data: { step: 'authenticated', user: { id: 1 } } })
    const { wrapper } = await mountLogin()
    await submit(wrapper, typed)
    expect(authService.signIn).toHaveBeenCalledWith({
      identifier: sent,
      password: 'Wafa-Charity-Account-2026',
    })
  })

  it('refuses an identifier that is neither an e-mail, a Saudi mobile nor a unified number', async () => {
    const { wrapper } = await mountLogin()
    await submit(wrapper, '12345')
    expect(authService.signIn).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('10 digits starting with 7')
  })

  it('opens the e-mail code step with the masked e-mail of the account', async () => {
    authService.signIn.mockResolvedValue({
      data: { step: 'otp_required', otp: { challengeId: 'c-1', length: 6 } },
    })
    const { wrapper, push } = await mountLogin()
    await submit(wrapper, 'Info@Alamal.example.sa')
    expect(push).toHaveBeenCalledWith({ name: 'auth.otp', query: undefined })
    expect(useAuthFlowStore().officialEmail).toBe('info@alamal.example.sa')
    expect(useAuthFlowStore().intent).toBe('login')
  })

  it.each(['mfa_challenge', 'mfa_enrollment'])(
    'opens the authenticator step for %s',
    async (step) => {
      authService.signIn.mockResolvedValue({ data: { step } })
      const { wrapper, push } = await mountLogin()
      await submit(wrapper, '7001234567')
      expect(push).toHaveBeenCalledWith({ name: 'auth.mfa', query: undefined })
      expect(useAuthFlowStore().pendingStep).toBe(step)
    },
  )

  it('shows one generic message for wrong credentials and clears the password', async () => {
    authService.signIn.mockRejectedValue(rejection(422, 'credentials.invalid'))
    const { wrapper } = await mountLogin()
    await submit(wrapper, 'info@alamal.example.sa')
    const alert = wrapper.find('.auth-failure')
    expect(alert.attributes('role')).toBe('alert')
    expect(alert.text()).toBe('The sign-in details are incorrect. Check them and try again.')
    expect(wrapper.find('[name="password"]').element.value).toBe('')
  })

  it('explains a locked account with the wait from Retry-After', async () => {
    authService.signIn.mockRejectedValue(rejection(423, 'account_locked', { 'retry-after': '840' }))
    const { wrapper } = await mountLogin('ar')
    await submit(wrapper, '0551234567')
    expect(wrapper.find('.auth-failure').attributes('data-failure')).toBe('locked')
    expect(wrapper.find('.auth-failure').text()).toBe(
      'أُقفل الحساب مؤقتًا بعد محاولات خاطئة متكررة. حاول مرة أخرى بعد 14 دقيقة.',
    )
  })

  it.each([
    [
      rejection(429, 'rate_limited', { 'retry-after': '45' }),
      'throttled',
      'Try again in 1 minute.',
    ],
    [rejection(403, 'account_deactivated'), 'deactivated', 'This account is not active.'],
    [rejection(500, 'server_error'), 'unexpected', 'Signing in is not possible right now.'],
  ])('maps a rejection to the %s message', async (error, kind, text) => {
    authService.signIn.mockRejectedValue(error)
    const { wrapper } = await mountLogin()
    await submit(wrapper, 'info@alamal.example.sa')
    expect(wrapper.find('.auth-failure').attributes('data-failure')).toBe(kind)
    expect(wrapper.find('.auth-failure').text()).toContain(text)
  })

  it('puts a server validation message on its field', async () => {
    authService.signIn.mockRejectedValue({
      response: { status: 422 },
      normalized: { code: 'validation_failed', fieldErrors: { identifier: ['Server format'] } },
    })
    const { wrapper } = await mountLogin()
    await submit(wrapper, 'info@alamal.example.sa')
    expect(wrapper.find('.auth-failure').exists()).toBe(false)
    expect(wrapper.text()).toContain('Server format')
  })

  it('renders no demo login unless the local-only build flag is set', async () => {
    expect(import.meta.env.VITE_DEMO_LOGIN).toBeUndefined()
    const { wrapper } = await mountLogin()
    expect(wrapper.find('.auth-form__demo').exists()).toBe(false)
  })

  it('confirms a completed password reset', async () => {
    const { wrapper } = await mountLogin('ar', '/auth/login?notice=password-reset')
    expect(wrapper.find('.auth-status--success').text()).toContain('تم تعيين كلمة المرور الجديدة')
  })
})
