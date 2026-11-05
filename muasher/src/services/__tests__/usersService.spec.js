import { describe, expect, it, vi, beforeEach } from 'vitest'
import { usersService } from '@/services/usersService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const envelope = { data: {}, meta: {}, errors: null }

beforeEach(() => {
  vi.clearAllMocks()
})

describe('usersService', () => {
  it('lists users, maps them and keeps a params-shape breadcrumb', async () => {
    apiClient.get.mockResolvedValue({
      data: {
        data: [
          {
            id: 4,
            name: 'سارة',
            email: 's@example.sa',
            roles: ['assessor'],
            status: 'invited',
            mfaEnabled: false,
            lastLoginAt: null,
          },
        ],
        meta: { stats: { total: 1, invited: 1 } },
        errors: null,
      },
    })
    const params = { search: 'ريم', 'filter[role]': 'assessor', page: 2 }
    const result = await usersService.list(params)
    expect(apiClient.get).toHaveBeenCalledWith('/users', { params })
    expect(result.data[0]).toMatchObject({
      id: 4,
      status: 'invited',
      mfaEnabled: false,
      lastLoginAt: null,
      phone: null,
    })
    expect(result.meta.stats).toEqual({
      total: 1,
      active: 0,
      locked: 0,
      suspended: 0,
      invited: 1,
      byRole: {},
    })
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'users.list',
      expect.objectContaining({ entity: 'user', paramsShape: ['search', 'filter[role]', 'page'] }),
    )
  })

  it('gets a single user by id', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await usersService.get(7)
    expect(apiClient.get).toHaveBeenCalledWith('/users/7')
  })

  it('creates a user via POST and logs only the payload shape (no PII)', async () => {
    apiClient.post.mockResolvedValue({ data: envelope })
    await usersService.create({
      name: 'فهد',
      email: 'fahad@moashir.example.sa',
      roles: ['system_admin'],
    })
    expect(apiClient.post).toHaveBeenCalledWith('/users', {
      name: 'فهد',
      email: 'fahad@moashir.example.sa',
      roles: ['system_admin'],
    })
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'users.create',
      expect.objectContaining({ payloadShape: ['name', 'email', 'roles'] }),
    )
  })

  it('updates a user via PUT', async () => {
    apiClient.put.mockResolvedValue({ data: envelope })
    await usersService.update(3, { roles: ['request_follower'] })
    expect(apiClient.put).toHaveBeenCalledWith('/users/3', { roles: ['request_follower'] })
  })

  it('calls the explicit account verbs, never a generic status patch', async () => {
    apiClient.post.mockResolvedValue({ data: envelope })
    await usersService.suspend(9, { reason: 'إيقاف مؤقت بقرار من الإدارة' })
    expect(apiClient.post).toHaveBeenCalledWith('/users/9/suspend', {
      reason: 'إيقاف مؤقت بقرار من الإدارة',
    })
    await usersService.activate(9)
    expect(apiClient.post).toHaveBeenCalledWith('/users/9/activate', {})
    await usersService.unlock(9)
    expect(apiClient.post).toHaveBeenCalledWith('/users/9/unlock', {})
    await usersService.resetMfa(9, { reason: 'فقد المستخدم جهاز المصادقة' })
    expect(apiClient.post).toHaveBeenCalledWith('/users/9/mfa/reset', {
      reason: 'فقد المستخدم جهاز المصادقة',
    })
    await usersService.resendInvitation(9)
    expect(apiClient.post).toHaveBeenCalledWith('/users/9/invitation/resend', {})
    expect(apiClient.patch).not.toHaveBeenCalled()
  })

  it('reports failures to Sentry and rethrows (never swallows)', async () => {
    const failure = new Error('boom')
    apiClient.get.mockRejectedValue(failure)
    await expect(usersService.list()).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'users.list' }),
    )
  })
})
