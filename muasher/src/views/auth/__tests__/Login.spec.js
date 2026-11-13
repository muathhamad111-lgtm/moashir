import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import Login from '@/views/auth/Login.vue'
import { authService } from '@/services/authService'
import { useAuthFlowStore } from '@/stores/authFlow'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/authService', () => ({
  UNAUTHENTICATED_STATUS: 401,
  authService: {
    signIn: vi.fn(),
    requestSignInCode: vi.fn(),
  },
}))

const CODE_SENT = {
  data: {
    step: 'otp_required',
    otp: { challengeId: 'c-1', channel: 'email', length: 6 },
    message: 'If an entity account matches what you entered, a sign-in code has been sent.',
  },
}

let wrapper

function rejection(status, code, headers = {}) {
  return { response: { status, headers }, normalized: { status, code, message: code } }
}

async function mountLogin(locale = 'en', path = '/auth/login', identifier = '') {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  if (identifier) useAuthFlowStore().setChallenge({ challenge: null, identifier })
  await globals.router.push(path)
  const push = vi.spyOn(globals.router, 'push')
  wrapper = mount(Login, { global: globals, attachTo: document.body })
  await flushPromises()
  return { wrapper, push }
}

async function settle() {
  for (let round = 0; round < 3; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

function tab(method) {
  return wrapper.find(`[role="tab"][data-method="${method}"]`)
}

async function submit(identifier, password = 'Wafa-Charity-Account-2026') {
  await wrapper.find('[name="identifier"]').setValue(identifier)
  await wrapper.find('[name="password"]').setValue(password)
  await wrapper.find('form').trigger('submit')
  await settle()
}

async function requestCode(identifier) {
  await tab('code').trigger('click')
  await wrapper.find('[name="identifier"]').setValue(identifier)
  await wrapper.find('form').trigger('submit')
  await settle()
}

afterEach(() => {
  wrapper?.unmount()
  document.documentElement.dir = ''
  vi.clearAllMocks()
})

describe('Login view: method tabs', () => {
  it('names the tablist and opens the password tab first', async () => {
    await mountLogin()
    const list = wrapper.find('[role="tablist"]')
    expect(list.attributes('aria-label')).toBe('Sign-in method')
    const tabs = wrapper.findAll('[role="tab"]')
    expect(tabs.map((item) => item.text())).toEqual(['Password', 'Verification code'])
    expect(tab('password').attributes()).toMatchObject({ 'aria-selected': 'true', tabindex: '0' })
    expect(tab('code').attributes()).toMatchObject({ 'aria-selected': 'false', tabindex: '-1' })
    const panel = wrapper.find('[role="tabpanel"]')
    expect(tab('password').attributes('aria-controls')).toBe(panel.attributes('id'))
    expect(panel.attributes('aria-labelledby')).toBe(tab('password').attributes('id'))
    expect(wrapper.find('[name="password"]').exists()).toBe(true)
  })

  it('preselects the code tab from ?method=code and prefills the identifier of the flow', async () => {
    await mountLogin('en', '/auth/login?method=code', 'info@alamal.example.sa')
    expect(tab('code').attributes('aria-selected')).toBe('true')
    expect(wrapper.find('[name="password"]').exists()).toBe(false)
    expect(wrapper.find('[name="identifier"]').element.value).toBe('info@alamal.example.sa')
  })

  it('keeps the identifier when switching tabs and drops the password', async () => {
    await mountLogin()
    await wrapper.find('[name="identifier"]').setValue('0551234567')
    await wrapper.find('[name="password"]').setValue('Wafa-Charity-Account-2026')
    await tab('code').trigger('click')
    expect(wrapper.find('[name="identifier"]').element.value).toBe('0551234567')
    await tab('password').trigger('click')
    expect(wrapper.find('[name="identifier"]').element.value).toBe('0551234567')
    expect(wrapper.find('[name="password"]').element.value).toBe('')
  })

  it.each([
    ['ltr', 'ArrowRight', 'code'],
    ['ltr', 'ArrowLeft', 'code'],
    ['rtl', 'ArrowLeft', 'code'],
    ['ltr', 'End', 'code'],
  ])('moves with the keyboard in %s: %s selects %s and focuses it', async (dir, key, method) => {
    document.documentElement.dir = dir
    await mountLogin(dir === 'rtl' ? 'ar' : 'en')
    await tab('password').trigger('keydown', { key })
    expect(tab(method).attributes('aria-selected')).toBe('true')
    expect(document.activeElement).toBe(tab(method).element)
  })

  it('returns to the first tab with Home and ignores other keys', async () => {
    await mountLogin('en', '/auth/login?method=code')
    await tab('code').trigger('keydown', { key: 'Enter' })
    expect(tab('code').attributes('aria-selected')).toBe('true')
    await tab('code').trigger('keydown', { key: 'Home' })
    expect(tab('password').attributes('aria-selected')).toBe('true')
  })
})

describe('Login view: password tab', () => {
  it('shows the identifier, the password and the forgotten-password link', async () => {
    await mountLogin()
    expect(wrapper.find('[name="identifier"]').attributes()).toMatchObject({
      autocomplete: 'username',
      dir: 'ltr',
    })
    expect(wrapper.find('label[for]').text()).toBe('E-mail, mobile or unified national number')
    expect(wrapper.find('[name="password"]').attributes('type')).toBe('password')
    expect(wrapper.find('.field__hint').exists()).toBe(false)
    expect(wrapper.find('.auth-form__forgot').attributes('href')).toBe('/auth/forgot-password')
  })

  it.each([
    ['info@alamal.example.sa', 'info@alamal.example.sa'],
    ['055 123 4567', '0551234567'],
    ['٧٠٠١٢٣٤٥٦٧', '7001234567'],
  ])('sends %s as the identifier %s with the password', async (typed, sent) => {
    authService.signIn.mockResolvedValue({ data: { step: 'authenticated', user: { id: 1 } } })
    const { push } = await mountLogin()
    await submit(typed)
    expect(authService.signIn).toHaveBeenCalledWith({
      identifier: sent,
      password: 'Wafa-Charity-Account-2026',
    })
    expect(push).toHaveBeenCalledWith({ name: 'app.dashboard' })
    expect(authService.requestSignInCode).not.toHaveBeenCalled()
  })

  it('refuses an identifier that is neither an e-mail, a Saudi mobile nor a unified number', async () => {
    await mountLogin()
    await submit('12345')
    expect(authService.signIn).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('10 digits starting with 7')
  })

  it('opens the registration code of a pending entity with the masked e-mail', async () => {
    authService.signIn.mockResolvedValue({
      data: { step: 'otp_required', otp: { challengeId: 'c-1', length: 6 } },
    })
    const { push } = await mountLogin()
    await submit('Info@Alamal.example.sa')
    expect(push).toHaveBeenCalledWith({ name: 'auth.otp', query: undefined })
    const flow = useAuthFlowStore()
    expect(flow.officialEmail).toBe('info@alamal.example.sa')
    expect(flow.identifier).toBe('Info@Alamal.example.sa')
    expect(flow.intent).toBe('login')
  })

  it.each(['mfa_challenge', 'mfa_enrollment'])(
    'opens the authenticator step for %s',
    async (step) => {
      authService.signIn.mockResolvedValue({ data: { step } })
      const { push } = await mountLogin()
      await submit('7001234567')
      expect(push).toHaveBeenCalledWith({ name: 'auth.mfa', query: undefined })
      expect(useAuthFlowStore().pendingStep).toBe(step)
    },
  )

  it('shows one generic message for wrong credentials and clears the password', async () => {
    authService.signIn.mockRejectedValue(rejection(422, 'credentials.invalid'))
    await mountLogin()
    await submit('info@alamal.example.sa')
    const alert = wrapper.find('.auth-failure')
    expect(alert.attributes('role')).toBe('alert')
    expect(alert.text()).toBe('The sign-in details are incorrect. Check them and try again.')
    expect(wrapper.find('[name="password"]').element.value).toBe('')
  })

  it('explains a locked account with the wait from Retry-After', async () => {
    authService.signIn.mockRejectedValue(rejection(423, 'account_locked', { 'retry-after': '840' }))
    await mountLogin('ar')
    await submit('0551234567')
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
    await mountLogin()
    await submit('info@alamal.example.sa')
    expect(wrapper.find('.auth-failure').attributes('data-failure')).toBe(kind)
    expect(wrapper.find('.auth-failure').text()).toContain(text)
  })

  it('puts a server validation message on its field', async () => {
    authService.signIn.mockRejectedValue({
      response: { status: 422 },
      normalized: { code: 'validation_failed', fieldErrors: { identifier: ['Server format'] } },
    })
    await mountLogin()
    await submit('info@alamal.example.sa')
    expect(wrapper.find('.auth-failure').exists()).toBe(false)
    expect(wrapper.text()).toContain('Server format')
  })

  it('renders no demo login unless the local-only build flag is set', async () => {
    expect(import.meta.env.VITE_DEMO_LOGIN).toBeUndefined()
    await mountLogin()
    expect(wrapper.find('.auth-form__demo').exists()).toBe(false)
  })

  it('confirms a completed password reset', async () => {
    await mountLogin('ar', '/auth/login?notice=password-reset')
    expect(wrapper.find('.auth-status--success').text()).toContain('تم تعيين كلمة المرور الجديدة')
  })
})

describe('Login view: code tab', () => {
  it('shows only the identifier and the send button', async () => {
    await mountLogin('ar', '/auth/login?method=code')
    expect(wrapper.find('[name="password"]').exists()).toBe(false)
    expect(wrapper.find('.auth-form__forgot').exists()).toBe(false)
    expect(wrapper.find('.field__hint').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('يُرسل الرمز')
    expect(wrapper.text()).not.toContain('لحسابات الجهات فقط')
    expect(wrapper.find('button[type="submit"]').text()).toBe('إرسال رمز التحقق')
  })

  it.each([
    ['info@alamal.example.sa', 'info@alamal.example.sa'],
    ['055 123 4567', '0551234567'],
    ['٧٠٠١٢٣٤٥٦٧', '7001234567'],
  ])('requests a code for %s with the identifier %s only', async (typed, sent) => {
    authService.requestSignInCode.mockResolvedValue(CODE_SENT)
    const { push } = await mountLogin()
    await requestCode(typed)
    expect(authService.requestSignInCode).toHaveBeenCalledWith({ identifier: sent })
    expect(authService.signIn).not.toHaveBeenCalled()
    expect(push).toHaveBeenCalledWith({ name: 'auth.otp', query: undefined })
    const flow = useAuthFlowStore()
    expect(flow.intent).toBe('code')
    expect(flow.identifier).toBe(typed.trim())
    expect(flow.officialEmail).toBe('')
    expect(flow.challenge.challengeId).toBe('c-1')
  })

  it('keeps the redirect target on the way to the code step', async () => {
    authService.requestSignInCode.mockResolvedValue(CODE_SENT)
    const { push } = await mountLogin('en', '/auth/login?redirect=%2Fassessment%2Fself')
    await requestCode('7001234567')
    expect(push).toHaveBeenCalledWith({
      name: 'auth.otp',
      query: { redirect: '/assessment/self' },
    })
  })

  it('refuses a malformed identifier with the same message as the password tab', async () => {
    await mountLogin()
    await requestCode('12345')
    expect(authService.requestSignInCode).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('10 digits starting with 7')
  })

  it('shows too many requests with the wait above the field', async () => {
    authService.requestSignInCode.mockRejectedValue(
      rejection(429, 'rate_limited', { 'retry-after': '540' }),
    )
    await mountLogin('ar')
    await requestCode('7001234567')
    const alert = wrapper.find('.auth-failure')
    expect(alert.attributes()).toMatchObject({ role: 'alert', 'data-failure': 'throttled' })
    expect(alert.text()).toBe('طلبات كثيرة. حاول مرة أخرى بعد 9 دقائق.')
    expect(wrapper.find('[name="identifier"]').element.value).toBe('7001234567')
  })

  it('shows an unexpected failure and a server field error', async () => {
    authService.requestSignInCode
      .mockRejectedValueOnce(rejection(500, 'server_error'))
      .mockRejectedValueOnce({
        response: { status: 422 },
        normalized: { code: 'validation_failed', fieldErrors: { identifier: ['Server format'] } },
      })
    await mountLogin()
    await requestCode('7001234567')
    expect(wrapper.find('.auth-failure').text()).toBe(
      'The code cannot be sent right now. Try again shortly.',
    )
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(wrapper.find('.auth-failure').exists()).toBe(false)
    expect(wrapper.text()).toContain('Server format')
  })
})
