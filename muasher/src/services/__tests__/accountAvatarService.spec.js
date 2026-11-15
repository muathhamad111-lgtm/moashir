import { beforeEach, describe, expect, it, vi } from 'vitest'
import { accountAvatarService } from '@/services/accountAvatarService'
import { toSessionUser } from '@/services/mappers/session'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { post: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

beforeEach(() => vi.clearAllMocks())

describe('accountAvatarService', () => {
  it('uploads the photo as multipart and maps the avatar link', async () => {
    apiClient.post.mockResolvedValue({
      data: { data: { avatar: { source: 'photo', url: '/api/v1/me/avatar?v=1' } } },
    })
    const file = new File(['x'], 'photo.png', { type: 'image/png' })
    const { data } = await accountAvatarService.upload(file)
    const [path, body] = apiClient.post.mock.calls[0]
    expect(path).toBe('/me/avatar')
    expect(body.get('file')).toBeInstanceOf(File)
    expect(data.avatar).toEqual({ source: 'photo', url: '/api/v1/me/avatar?v=1' })
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'accountAvatar.upload',
      expect.objectContaining({ payloadShape: ['file'] }),
    )
  })

  it('removes the photo and maps the empty avatar', async () => {
    apiClient.delete.mockResolvedValue({ data: { data: { avatar: null } } })
    const { data } = await accountAvatarService.remove()
    expect(apiClient.delete).toHaveBeenCalledWith('/me/avatar')
    expect(data.avatar).toBeNull()
  })

  it('does not report an expected validation refusal as an error', async () => {
    apiClient.post.mockRejectedValue({ response: { status: 422 } })
    await expect(
      accountAvatarService.upload(new File(['x'], 'a.gif', { type: 'image/gif' })),
    ).rejects.toBeTruthy()
    expect(logger.error).not.toHaveBeenCalled()
  })
})

describe('session avatar mapping', () => {
  it('reads the avatar link of the session and treats a missing one as none', () => {
    expect(
      toSessionUser({ id: 1, avatar: { source: 'entity_logo', url: '/api/v1/me/avatar?v=2' } })
        .avatar,
    ).toEqual({ source: 'entity_logo', url: '/api/v1/me/avatar?v=2' })
    expect(toSessionUser({ id: 1 }).avatar).toBeNull()
    expect(toSessionUser({ id: 1, avatar: { source: 'photo' } }).avatar).toBeNull()
  })
})
