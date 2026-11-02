import { describe, expect, it, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { authService } from '@/services/authService'
import { LOGIN_STEPS } from '@/constants/loginSteps'

vi.mock('@/services/authService', () => ({
  UNAUTHENTICATED_STATUS: 401,
  authService: {
    register: vi.fn(),
    loginWithPassword: vi.fn(),
    requestLoginOtp: vi.fn(),
    verifyOtp: vi.fn(),
    resendOtp: vi.fn(),
    challengeMfa: vi.fn(),
    startMfaEnrollment: vi.fn(),
    confirmMfaEnrollment: vi.fn(),
    logout: vi.fn(),
    session: vi.fn(),
  },
}))

const user = {
  id: 5001,
  name: 'Entity',
  roles: ['entity'],
  permissions: ['dashboard.view'],
  isSuperAdmin: false,
  committeeAppointments: [],
  profileCompleted: false,
  status: 'active',
}

function storageSnapshot() {
  return JSON.stringify({ ...localStorage }) + JSON.stringify({ ...sessionStorage })
}

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
  sessionStorage.clear()
  vi.clearAllMocks()
})

describe('auth store — cookie session, nothing in browser storage', () => {
  it('keeps the signed-in user in memory only', () => {
    const auth = useAuthStore()
    auth.setUser(user)
    expect(auth.isAuthenticated).toBe(true)
    expect(auth.isEntity).toBe(true)
    expect(auth.permissions).toEqual(['dashboard.view'])
    expect(localStorage.length).toBe(0)
    expect(sessionStorage.length).toBe(0)
  })

  it('loads the session from the server once and reuses it', async () => {
    authService.session.mockResolvedValue({ data: user })
    const auth = useAuthStore()
    await Promise.all([auth.ensureSession(), auth.ensureSession()])
    await auth.ensureSession()
    expect(authService.session).toHaveBeenCalledTimes(1)
    expect(auth.user).toEqual(user)
    expect(storageSnapshot()).toBe('{}{}')
  })

  it('treats a 401 from /auth/session as a guest without retrying', async () => {
    authService.session.mockRejectedValue({ response: { status: 401 } })
    const auth = useAuthStore()
    await auth.ensureSession()
    await auth.ensureSession()
    expect(auth.isAuthenticated).toBe(false)
    expect(auth.sessionChecked).toBe(true)
    expect(authService.session).toHaveBeenCalledTimes(1)
  })

  it('retries the session check on the next navigation after a network failure', async () => {
    authService.session.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce({ data: user })
    const auth = useAuthStore()
    await auth.ensureSession()
    expect(auth.isAuthenticated).toBe(false)
    await auth.ensureSession()
    expect(auth.isAuthenticated).toBe(true)
  })

  it('patchUser updates the in-memory user only', () => {
    const auth = useAuthStore()
    auth.setUser(user)
    auth.patchUser({ profileCompleted: true })
    expect(auth.profileCompleted).toBe(true)
    expect(localStorage.length).toBe(0)
  })

  it('register returns the OTP challenge without opening a session', async () => {
    authService.register.mockResolvedValue({
      data: { step: LOGIN_STEPS.OTP_REQUIRED, otp: { challengeId: '9' }, user: null },
    })
    const auth = useAuthStore()
    const data = await auth.register({ officialEmail: 'x@example.sa' })
    expect(data.otp.challengeId).toBe('9')
    expect(auth.isAuthenticated).toBe(false)
  })

  it('opens the session only when the step is authenticated', async () => {
    authService.loginWithPassword.mockResolvedValue({ data: { step: LOGIN_STEPS.AUTHENTICATED, user } })
    const auth = useAuthStore()
    const outcome = await auth.loginWithPassword({ officialEmail: 'x@example.sa', password: 'secret' })
    expect(outcome.step).toBe(LOGIN_STEPS.AUTHENTICATED)
    expect(auth.user).toEqual(user)
  })

  it.each([LOGIN_STEPS.MFA_CHALLENGE, LOGIN_STEPS.MFA_ENROLLMENT, LOGIN_STEPS.OTP_REQUIRED])(
    'stays signed out while the %s step is pending',
    async (step) => {
      authService.loginWithPassword.mockResolvedValue({ data: { step, user: null } })
      const auth = useAuthStore()
      await auth.loginWithPassword({ officialEmail: 'x@example.sa', password: 'secret' })
      expect(auth.isAuthenticated).toBe(false)
    },
  )

  it('completes password → OTP → TOTP and only then holds the user', async () => {
    authService.requestLoginOtp.mockResolvedValue({ data: { step: LOGIN_STEPS.OTP_REQUIRED, otp: { challengeId: '11' } } })
    authService.verifyOtp.mockResolvedValue({ data: { step: LOGIN_STEPS.MFA_CHALLENGE, user: null } })
    authService.challengeMfa.mockResolvedValue({ data: { step: LOGIN_STEPS.AUTHENTICATED, user } })
    const auth = useAuthStore()
    await auth.requestLoginOtp({ officialEmail: 'x@example.sa', channel: 'email' })
    await auth.verifyOtp({ challengeId: '11', code: '000000' })
    expect(auth.isAuthenticated).toBe(false)
    await auth.challengeMfa({ code: '000000' })
    expect(auth.isAuthenticated).toBe(true)
  })

  it('confirms the MFA enrolment and opens the session', async () => {
    authService.confirmMfaEnrollment.mockResolvedValue({
      data: { step: LOGIN_STEPS.AUTHENTICATED, user, recoveryCodes: ['A-1'] },
    })
    const auth = useAuthStore()
    const outcome = await auth.confirmMfaEnrollment({ code: '000000' })
    expect(outcome.recoveryCodes).toEqual(['A-1'])
    expect(auth.isAuthenticated).toBe(true)
  })

  it('logout clears the session even if the service call fails', async () => {
    authService.logout.mockRejectedValue(new Error('offline'))
    const auth = useAuthStore()
    auth.setUser(user)
    const signedOut = await auth.logout()
    expect(signedOut).toBe(false)
    expect(auth.isAuthenticated).toBe(false)
  })
})
