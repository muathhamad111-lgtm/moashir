import { describe, expect, it, vi, beforeEach } from 'vitest'
import { authService } from '@/services/authService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const AUTH_FLOW = { skipSessionExpiry: true }

function respond(data) {
  return { data: { data, meta: {}, errors: null } }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('authService', () => {
  it('loads the login modes', async () => {
    apiClient.get.mockResolvedValue(respond({ emailPassword: true }))
    const result = await authService.getLoginModes()
    expect(apiClient.get).toHaveBeenCalledWith('/auth/login-modes', AUTH_FLOW)
    expect(result.data.emailPassword).toBe(true)
  })

  it('registers via POST and breadcrumbs only the field SHAPE (never values)', async () => {
    apiClient.post.mockResolvedValue(respond({ step: 'otp_required', otp: { challengeId: '1' } }))
    const payload = { officialEmail: 'a@example.sa', password: 'Secret@123', entityType: 'waqf' }
    const result = await authService.register(payload)
    expect(apiClient.post).toHaveBeenCalledWith('/auth/register', payload, AUTH_FLOW)
    expect(result.data.otp.challengeId).toBe('1')
    const serialized = JSON.stringify(logger.breadcrumb.mock.calls[0][2])
    expect(serialized).toContain('password')
    expect(serialized).not.toContain('Secret@123')
  })

  it('signs in with the contract field names and maps the authenticated user', async () => {
    apiClient.post.mockResolvedValue(
      respond({
        step: 'authenticated',
        user: {
          id: 7,
          name: 'User',
          roles: [{ key: 'assessor', label: { ar: 'المقيّم', en: 'Assessor' } }],
          permissions: ['dashboard.view'],
          isSuperAdmin: false,
          committeeAppointments: [{ cycleId: 3 }],
        },
      }),
    )
    const result = await authService.loginWithPassword({
      officialEmail: 'a@example.sa',
      password: 'x',
    })
    expect(apiClient.post).toHaveBeenCalledWith(
      '/auth/login',
      { email: 'a@example.sa', mode: 'password', password: 'x' },
      AUTH_FLOW,
    )
    expect(result.data.step).toBe('authenticated')
    expect(result.data.user.roles).toEqual(['assessor'])
    expect(result.data.user.permissions).toEqual(['dashboard.view'])
    expect(result.data.user.committeeAppointments).toEqual([{ cycleId: 3 }])
  })

  it('requests an OTP sign-in through the login endpoint', async () => {
    apiClient.post.mockResolvedValue(respond({ step: 'otp_required', otp: { challengeId: 'c-1' } }))
    const result = await authService.requestLoginOtp({
      officialEmail: 'a@example.sa',
      channel: 'sms',
    })
    expect(apiClient.post).toHaveBeenCalledWith(
      '/auth/login',
      { email: 'a@example.sa', mode: 'otp', channel: 'sms' },
      AUTH_FLOW,
    )
    expect(result.data.otp.challengeId).toBe('c-1')
    expect(result.data.user).toBeNull()
  })

  it('maps the e-mail field error back to the form field name', async () => {
    const error = { normalized: { fieldErrors: { email: ['Invalid'] } } }
    apiClient.post.mockRejectedValue(error)
    await expect(
      authService.loginWithPassword({ officialEmail: 'a@example.sa', password: 'x' }),
    ).rejects.toBe(error)
    expect(error.normalized.fieldErrors).toEqual({ officialEmail: ['Invalid'] })
  })

  it('verifies an OTP without leaking the code value', async () => {
    apiClient.post.mockResolvedValue(respond({ step: 'mfa_challenge' }))
    const result = await authService.verifyOtp({ challengeId: '900', code: '482913' })
    expect(apiClient.post).toHaveBeenCalledWith(
      '/auth/otp/verify',
      { challengeId: '900', code: '482913' },
      AUTH_FLOW,
    )
    expect(result.data.step).toBe('mfa_challenge')
    expect(JSON.stringify(logger.breadcrumb.mock.calls)).not.toContain('482913')
  })

  it('sends either the TOTP code or a recovery code to the MFA challenge', async () => {
    apiClient.post.mockResolvedValue(respond({ step: 'authenticated', user: { id: 1, roles: [] } }))
    await authService.challengeMfa({ code: '123456' })
    await authService.challengeMfa({ recoveryCode: 'ABCD-1234' })
    expect(apiClient.post).toHaveBeenNthCalledWith(
      1,
      '/auth/mfa/challenge',
      { code: '123456' },
      AUTH_FLOW,
    )
    expect(apiClient.post).toHaveBeenNthCalledWith(
      2,
      '/auth/mfa/challenge',
      { recoveryCode: 'ABCD-1234' },
      AUTH_FLOW,
    )
  })

  it('starts and confirms the MFA enrolment with POST', async () => {
    apiClient.post.mockResolvedValueOnce(respond({ qrSvg: '<svg/>', manualEntryKey: 'KEY' }))
    apiClient.post.mockResolvedValueOnce(
      respond({ step: 'authenticated', user: { id: 1 }, recoveryCodes: ['R-1'] }),
    )
    const enrollment = await authService.startMfaEnrollment()
    const confirmed = await authService.confirmMfaEnrollment({ code: '123456' })
    expect(apiClient.post).toHaveBeenNthCalledWith(1, '/auth/mfa/enroll', {}, AUTH_FLOW)
    expect(enrollment.data).toEqual({ qrSvg: '<svg/>', manualEntryKey: 'KEY' })
    expect(confirmed.data.recoveryCodes).toEqual(['R-1'])
  })

  it('reads the session without reporting the expected 401 of a guest', async () => {
    apiClient.get.mockRejectedValue({ response: { status: 401 } })
    await expect(authService.session()).rejects.toEqual({ response: { status: 401 } })
    expect(apiClient.get).toHaveBeenCalledWith('/auth/session', AUTH_FLOW)
    expect(logger.error).not.toHaveBeenCalled()
  })

  it('reports an unexpected session failure', async () => {
    apiClient.get.mockRejectedValue({ response: { status: 500 } })
    await expect(authService.session()).rejects.toBeTruthy()
    expect(logger.error).toHaveBeenCalled()
  })

  it('logs out with POST', async () => {
    apiClient.post.mockResolvedValue({ data: '' })
    await authService.logout()
    expect(apiClient.post).toHaveBeenCalledWith('/auth/logout', {}, AUTH_FLOW)
  })
})
