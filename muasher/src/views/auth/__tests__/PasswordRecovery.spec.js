import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import ForgotPassword from '@/views/auth/ForgotPassword.vue'
import ResetPassword from '@/views/auth/ResetPassword.vue'
import AcceptInvitation from '@/views/auth/AcceptInvitation.vue'
import { authService } from '@/services/authService'
import { termsService } from '@/services/termsService'
import { createTestGlobals } from '@/test-utils'
import { ROUTE_NAMES } from '@/constants/routeNames'

vi.mock('@/services/authService', () => ({
  UNAUTHENTICATED_STATUS: 401,
  authService: {
    requestPasswordReset: vi.fn(),
    resetPassword: vi.fn(),
    acceptInvitation: vi.fn(),
    session: vi.fn(),
  },
}))
vi.mock('@/services/termsService', () => ({ termsService: { current: vi.fn() } }))

const STRONG = 'Namaa-Strong-2026!'
const TERMS = {
  terms: { id: 3, version: '1.2', body: { ar: 'نص', en: 'Text' } },
  privacy: { id: 4, version: '1.1', body: { ar: 'نص', en: 'Text' } },
}

let wrapper

async function settle() {
  for (let round = 0; round < 3; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

function mountView(component, props = {}, path = '/auth/login') {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  globals.router.push(path)
  wrapper = mount(component, { props, global: globals, attachTo: document.body })
  return globals
}

function fill(name, value) {
  return wrapper.find(`[name="${name}"]`).setValue(value)
}

function rejection(status, code, fieldErrors = {}) {
  return Object.assign(new Error(code), {
    normalized: { status, code, message: code, fieldErrors },
  })
}

beforeEach(() => {
  termsService.current.mockResolvedValue({ data: TERMS })
})

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('forgot password', () => {
  it('always answers with the same neutral message after a valid request', async () => {
    authService.requestPasswordReset.mockResolvedValue({ data: { message: 'sent' } })
    mountView(ForgotPassword)
    await fill('email', ' staff@moashir.example.sa ')
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(authService.requestPasswordReset).toHaveBeenCalledWith({
      email: 'staff@moashir.example.sa',
    })
    expect(wrapper.find('.auth-status--success').text()).toContain('If this e-mail is registered')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('checks the e-mail format before calling the server', async () => {
    mountView(ForgotPassword)
    await fill('email', 'not-an-email')
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(authService.requestPasswordReset).not.toHaveBeenCalled()
    expect(wrapper.find('.field__error').exists()).toBe(true)
  })
})

describe('reset password', () => {
  it('sends the token with the new password and returns to sign in with a notice', async () => {
    authService.resetPassword.mockResolvedValue({ data: { message: 'reset' } })
    const globals = mountView(ResetPassword, { token: 'a1b2c3d4e5f6' })
    await fill('email', 'staff@moashir.example.sa')
    await fill('password', STRONG)
    await fill('passwordConfirmation', STRONG)
    expect(wrapper.find('.password-policy').text()).toContain('last five passwords')
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(authService.resetPassword).toHaveBeenCalledWith({
      token: 'a1b2c3d4e5f6',
      email: 'staff@moashir.example.sa',
      password: STRONG,
      passwordConfirmation: STRONG,
    })
    await vi.waitFor(() =>
      expect(globals.router.currentRoute.value.query.notice).toBe('password-reset'),
    )
    expect(globals.router.currentRoute.value.name).toBe(ROUTE_NAMES.AUTH_LOGIN)
  })

  it('refuses a short or unmatched password on the client', async () => {
    mountView(ResetPassword, { token: 'a1b2c3d4e5f6' })
    await fill('email', 'staff@moashir.example.sa')
    await fill('password', 'short')
    await fill('passwordConfirmation', 'other')
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(authService.resetPassword).not.toHaveBeenCalled()
    expect(wrapper.findAll('.field__error').length).toBeGreaterThan(0)
  })

  it('explains an invalid or expired link and offers a new one', async () => {
    authService.resetPassword.mockImplementation(
      () =>
        new Promise((resolve, reject) =>
          setTimeout(() => reject(rejection(422, 'token.invalid')), 15),
        ),
    )
    mountView(ResetPassword, { token: 'a1b2c3d4e5f6' })
    await fill('email', 'staff@moashir.example.sa')
    await fill('password', STRONG)
    await fill('passwordConfirmation', STRONG)
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(wrapper.find('.auth-status--danger').text()).toContain('invalid')
    expect(wrapper.find('.auth-status--danger a').attributes('href')).toBe('/auth/forgot-password')
  })
})

describe('invitation', () => {
  async function fillInvitation() {
    await settle()
    await fill('password', STRONG)
    await fill('passwordConfirmation', STRONG)
  }

  it('needs the current terms and sends their version with the password', async () => {
    authService.acceptInvitation.mockResolvedValue({
      data: { step: 'mfa_enrollment', user: null },
    })
    const globals = mountView(AcceptInvitation, { token: 'invite-sarah-whitfield-2026' })
    await fillInvitation()
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(authService.acceptInvitation).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('version 1.2')
    await wrapper.find('[name="termsAccepted"]').setValue(true)
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(authService.acceptInvitation).toHaveBeenCalledWith('invite-sarah-whitfield-2026', {
      password: STRONG,
      passwordConfirmation: STRONG,
      termsVersionId: 3,
    })
    await vi.waitFor(() =>
      expect(globals.router.currentRoute.value.name).toBe(ROUTE_NAMES.AUTH_MFA),
    )
  })

  it.each([
    [410, 'invitation.expired', 'The invitation has expired'],
    [404, 'invitation.invalid', 'The invitation link is not valid'],
  ])('shows the %s state of the token', async (status, code, title) => {
    authService.acceptInvitation.mockImplementation(
      () => new Promise((resolve, reject) => setTimeout(() => reject(rejection(status, code)), 15)),
    )
    mountView(AcceptInvitation, { token: 'invite-expired-2026' })
    await fillInvitation()
    await wrapper.find('[name="termsAccepted"]').setValue(true)
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(wrapper.find('.auth-status--danger').text()).toContain(title)
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('lets the user retry when the terms cannot be loaded', async () => {
    termsService.current.mockRejectedValueOnce(new Error('offline'))
    mountView(AcceptInvitation, { token: 'invite-sarah-whitfield-2026' })
    await settle()
    expect(wrapper.find('form').exists()).toBe(false)
    await wrapper.find('.error-state button').trigger('click')
    await settle()
    expect(wrapper.find('form').exists()).toBe(true)
  })
})
