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
  it('lists users and unwraps the envelope with a params-shape breadcrumb', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    const params = { search: 'ريم', 'filter[role]': 'assessor', page: 2 }
    const result = await usersService.list(params)
    expect(apiClient.get).toHaveBeenCalledWith('/users', { params })
    expect(result).toEqual(envelope)
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
      status: 'active',
    })
    expect(apiClient.post).toHaveBeenCalledWith('/users', {
      name: 'فهد',
      email: 'fahad@moashir.example.sa',
      roles: ['system_admin'],
      status: 'active',
    })
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'users.create',
      expect.objectContaining({ payloadShape: ['name', 'email', 'roles', 'status'] }),
    )
  })

  it('updates a user via PUT', async () => {
    apiClient.put.mockResolvedValue({ data: envelope })
    await usersService.update(3, { roles: ['request_follower'] })
    expect(apiClient.put).toHaveBeenCalledWith('/users/3', { roles: ['request_follower'] })
  })

  it('activates a user via PATCH with an active status body', async () => {
    apiClient.patch.mockResolvedValue({ data: envelope })
    await usersService.activate(9)
    expect(apiClient.patch).toHaveBeenCalledWith('/users/9/status', { status: 'active' })
  })

  it('deactivates a user via PATCH with an inactive status body', async () => {
    apiClient.patch.mockResolvedValue({ data: envelope })
    await usersService.deactivate(9)
    expect(apiClient.patch).toHaveBeenCalledWith('/users/9/status', { status: 'inactive' })
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
