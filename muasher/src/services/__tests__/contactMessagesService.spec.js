import { beforeEach, describe, expect, it, vi } from 'vitest'
import { contactMessagesService } from '@/services/contactMessagesService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({ apiClient: { post: vi.fn(), get: vi.fn(), patch: vi.fn() } }))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

beforeEach(() => vi.clearAllMocks())

function rejection(status) {
  return Object.assign(new Error(`HTTP ${status}`), { response: { status } })
}

describe('contactMessagesService', () => {
  it('posts the message to the public contact endpoint and logs only the payload shape', async () => {
    apiClient.post.mockResolvedValue({ data: { data: { reference: 'CNT-2026-000014' } } })
    const message = { enquiryType: 'general', message: 'نص الرسالة للمراجعة', privacyConsent: true }
    const envelope = await contactMessagesService.submit(message)
    expect(apiClient.post).toHaveBeenCalledWith('/contact-messages', message)
    expect(envelope.data.reference).toBe('CNT-2026-000014')
    expect(JSON.stringify(logger.breadcrumb.mock.calls)).not.toContain('نص الرسالة')
  })

  it.each([422, 429])('treats a %s answer as an expected rejection', async (status) => {
    apiClient.post.mockRejectedValue(rejection(status))
    await expect(contactMessagesService.submit({})).rejects.toThrow('HTTP')
    expect(logger.error).not.toHaveBeenCalled()
  })

  it('reports unexpected failures', async () => {
    apiClient.post.mockRejectedValue(rejection(500))
    await expect(contactMessagesService.submit({})).rejects.toThrow('HTTP')
    expect(logger.error).toHaveBeenCalledTimes(1)
  })

  it('reads, marks seen and answers one own thread', async () => {
    const id = '7c2f4e1a-52b0-4c8e-9a31-0000000000a1'
    apiClient.get.mockResolvedValue({ data: { data: { id } } })
    apiClient.patch.mockResolvedValue({ data: { data: { id, hasUnreadReply: false } } })
    apiClient.post.mockResolvedValue({ data: { data: { id } } })
    await contactMessagesService.getMine(id)
    await contactMessagesService.markSeen(id)
    await contactMessagesService.reply(id, 'شكراً لكم')
    expect(apiClient.get).toHaveBeenCalledWith(`/me/contact-messages/${id}`)
    expect(apiClient.patch).toHaveBeenCalledWith(`/me/contact-messages/${id}/seen`)
    expect(apiClient.post).toHaveBeenCalledWith(`/me/contact-messages/${id}/replies`, {
      body: 'شكراً لكم',
    })
    expect(JSON.stringify(logger.breadcrumb.mock.calls)).not.toContain('شكراً')
  })

  it.each([404, 409])('treats a %s reply answer as expected', async (status) => {
    apiClient.post.mockRejectedValue(rejection(status))
    await expect(contactMessagesService.reply('id', 'نص')).rejects.toThrow('HTTP')
    expect(logger.error).not.toHaveBeenCalled()
  })
})
