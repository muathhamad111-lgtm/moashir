import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { withSetup } from '@/test-utils/withSetup'
import { useMyMessages } from '@/composables/useMyMessages'
import { useMessageThread } from '@/composables/useMessageThread'
import { useMessageReply } from '@/composables/useMessageReply'
import { contactMessagesService } from '@/services/contactMessagesService'
import { usePortalCountsStore } from '@/stores/portalCounts'

vi.mock('@/services/contactMessagesService', () => ({
  contactMessagesService: {
    listMine: vi.fn(),
    getMine: vi.fn(),
    markSeen: vi.fn(),
    reply: vi.fn(),
  },
}))

const THREAD = {
  id: 't-1',
  reference: 'CNT-2026-000052',
  status: 'replied',
  hasUnreadReply: true,
  canReply: true,
  entries: [],
}

function rejection(status, code, fieldErrors = {}) {
  return Object.assign(new Error(code), { normalized: { status, code, fieldErrors, message: '' } })
}

beforeEach(() => {
  vi.clearAllMocks()
  contactMessagesService.listMine.mockResolvedValue({
    data: [{ ...THREAD }],
    meta: {
      statusCounts: { new: 1, inProgress: 1, replied: 1, closed: 2 },
      unreadReplies: 1,
      pagination: { lastPage: 1 },
    },
  })
})

describe('useMyMessages', () => {
  it('loads the open tab with a status list and counts open as new + in progress + replied', async () => {
    const { api } = withSetup(() => useMyMessages())
    await flushPromises()
    expect(contactMessagesService.listMine).toHaveBeenCalledWith(
      expect.objectContaining({ 'filter[status]': 'new,in_progress,replied' }),
    )
    expect(api.tabs.value.map((tab) => tab.count)).toEqual([3, 2])
    expect(usePortalCountsStore().unreadReplies).toBe(1)
  })

  it('switches to the closed tab and merges an updated thread into the list', async () => {
    const { api } = withSetup(() => useMyMessages())
    await flushPromises()
    api.tab.value = 'closed'
    await vi.waitFor(() =>
      expect(contactMessagesService.listMine).toHaveBeenLastCalledWith(
        expect.objectContaining({ 'filter[status]': 'closed' }),
      ),
    )
    await flushPromises()
    api.applyThread({ ...THREAD, hasUnreadReply: false, entries: [{ id: 1 }] })
    expect(api.items.value[0].hasUnreadReply).toBe(false)
    expect(api.items.value[0].entries).not.toEqual([{ id: 1 }])
  })
})

describe('useMessageThread', () => {
  it('marks an unread reply seen once the thread opens', async () => {
    contactMessagesService.getMine.mockResolvedValue({ data: { ...THREAD } })
    contactMessagesService.markSeen.mockResolvedValue({
      data: { ...THREAD, hasUnreadReply: false },
    })
    const onChange = vi.fn()
    const { api } = withSetup(() => useMessageThread(ref('t-1'), { onChange }))
    await flushPromises()
    expect(contactMessagesService.markSeen).toHaveBeenCalledWith('t-1')
    expect(api.thread.value.hasUnreadReply).toBe(false)
    expect(onChange).toHaveBeenCalled()
  })

  it('reports another sender’s thread as missing', async () => {
    contactMessagesService.getMine.mockRejectedValue(rejection(404, 'not_found'))
    const { api } = withSetup(() => useMessageThread(ref('other')))
    await flushPromises()
    expect(api.status.value).toBe('missing')
    expect(contactMessagesService.markSeen).not.toHaveBeenCalled()
  })
})

describe('useMessageReply', () => {
  function setup() {
    const onReplied = vi.fn()
    const onClosed = vi.fn()
    const { api } = withSetup(() =>
      useMessageReply({ thread: ref({ ...THREAD }), onReplied, onClosed }),
    )
    return { api, onReplied, onClosed }
  }

  it('validates the length before sending and clears the draft after a reply', async () => {
    const { api, onReplied } = setup()
    api.draft.value = 'أ'
    await api.send()
    expect(contactMessagesService.reply).not.toHaveBeenCalled()
    expect(api.error.value).not.toBe('')
    contactMessagesService.reply.mockResolvedValue({ data: { ...THREAD, status: 'in-progress' } })
    api.draft.value = '  شكراً لكم على الرد  '
    await api.send()
    expect(contactMessagesService.reply).toHaveBeenCalledWith('t-1', 'شكراً لكم على الرد')
    expect(onReplied).toHaveBeenCalled()
    expect(api.draft.value).toBe('')
  })

  it('handles a closed thread, the rate limit and a field error', async () => {
    const { api, onClosed } = setup()
    api.draft.value = 'رد جديد على الاستفسار'
    contactMessagesService.reply.mockRejectedValueOnce(rejection(409, 'contact.message_closed'))
    await api.send()
    expect(onClosed).toHaveBeenCalled()
    contactMessagesService.reply.mockRejectedValueOnce(rejection(429, 'rate_limited'))
    await api.send()
    expect(api.error.value).toContain('many')
    contactMessagesService.reply.mockRejectedValueOnce(
      rejection(422, 'validation_failed', { body: ['The body is too long.'] }),
    )
    await api.send()
    expect(api.error.value).toBe('The body is too long.')
  })
})
