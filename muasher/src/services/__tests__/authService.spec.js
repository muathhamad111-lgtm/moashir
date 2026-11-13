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
    const result = await authService.signIn({ identifier: 'a@example.sa', password: 'x' })
    expect(apiClient.post).toHaveBeenCalledWith(
      '/auth/login',
      { identifier: 'a@example.sa', password: 'x' },
      AUTH_FLOW,
    )
    expect(result.data.step).toBe('authenticated')
    expect(result.data.user.roles).toEqual(['assessor'])
    expect(result.data.user.permissions).toEqual(['dashboard.view'])
    expect(result.data.user.committeeAppointments).toEqual([{ cycleId: 3 }])
  })

  it('requests a sign-in code with the identifier only and maps the challenge', async () => {
    apiClient.post.mockResolvedValue(
      respond({
        step: 'otp_required',
        requiresOtp: true,
        otp: { challengeId: 'c-1', channel: 'sms', length: 6 },
        message: 'If an entity account matches what you entered, a sign-in code has been sent.',
      }),
    )
    const result = await authService.requestSignInCode({ identifier: '0551234567' })
    expect(apiClient.post).toHaveBeenCalledWith(
      '/auth/otp/request',
      { identifier: '0551234567' },
      AUTH_FLOW,
    )
    expect(result.data).toMatchObject({
      step: 'otp_required',
      otp: { challengeId: 'c-1', channel: 'sms' },
      user: null,
    })
    expect(JSON.stringify(logger.breadcrumb.mock.calls)).not.toContain('0551234567')
  })

  it('treats 422 and 429 of a code request as expected answers', async () => {
    for (const status of [422, 429]) {
      apiClient.post.mockRejectedValueOnce({ response: { status } })
      await expect(
        authService.requestSignInCode({ identifier: 'a@example.sa' }),
      ).rejects.toBeTruthy()
    }
    expect(logger.error).not.toHaveBeenCalled()
  })

  it('signs an entity in with the password alone', async () => {
    apiClient.post.mockResolvedValue(respond({ step: 'authenticated', user: { id: 5001 } }))
    const result = await authService.signIn({ identifier: '7001234567', password: 'x' })
    expect(result.data.step).toBe('authenticated')
    expect(result.data.otp).toBeNull()
    expect(apiClient.post.mock.calls.map(([path]) => path)).toEqual(['/auth/login'])
  })

  it('does not report expected sign-in rejections to GlitchTip', async () => {
    for (const status of [403, 422, 423, 429]) {
      apiClient.post.mockRejectedValueOnce({ response: { status } })
      await expect(
        authService.signIn({ identifier: 'a@example.sa', password: 'x' }),
      ).rejects.toBeTruthy()
    }
    expect(logger.error).not.toHaveBeenCalled()
    apiClient.post.mockRejectedValueOnce({ response: { status: 500 } })
    await expect(
      authService.signIn({ identifier: 'a@example.sa', password: 'x' }),
    ).rejects.toBeTruthy()
    expect(logger.error).toHaveBeenCalledTimes(1)
  })

  it('resends the code with the challenge id only', async () => {
    apiClient.post.mockResolvedValue(respond({ step: 'otp_required', otp: { challengeId: 'c-2' } }))
    await authService.resendOtp({ challengeId: 'c-1' })
    expect(apiClient.post).toHaveBeenCalledWith(
      '/auth/otp/resend',
      { challengeId: 'c-1' },
      AUTH_FLOW,
    )
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
