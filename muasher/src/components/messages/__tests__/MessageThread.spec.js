import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import MessageThread from '@/components/messages/MessageThread.vue'
import MessageThreadItem from '@/components/messages/MessageThreadItem.vue'
import { contactMessagesService } from '@/services/contactMessagesService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/contactMessagesService', () => ({ contactMessagesService: { reply: vi.fn() } }))

const OPEN = {
  id: '7c2f4e1a-52b0-4c8e-9a31-0000000000a1',
  reference: 'CNT-2026-000052',
  status: 'replied',
  enquiryType: 'accredited_assessment',
  canReply: true,
  createdAt: '2026-11-02T07:14:00Z',
  closedAt: null,
  hasUnreadReply: true,
  preview: 'شكراً لتواصلك',
  lastActivityAt: '2026-11-03T09:40:00Z',
  entries: [
    {
      id: 1,
      kind: 'message',
      author: 'sender',
      body: 'سؤال عن الشواهد',
      createdAt: '2026-11-02T07:14:00Z',
    },
    {
      id: 2,
      kind: 'reply',
      author: 'team',
      body: 'شكراً لتواصلك',
      createdAt: '2026-11-03T09:40:00Z',
    },
  ],
}

function mountThread(thread = OPEN) {
  return mount(MessageThread, {
    props: { thread, senderName: 'سارة عبدالله', senderEmail: 'sara@alamal.example.sa' },
    global: createTestGlobals(),
  })
}

beforeEach(() => vi.clearAllMocks())

describe('MessageThread', () => {
  it('shows the enquiry type as the title, day groups, both authors and the e-mail copy', () => {
    const wrapper = mountThread()
    expect(wrapper.find('.message-thread__title').text()).toBe('التقييم المعتمد')
    expect(wrapper.findAll('.message-thread__day')).toHaveLength(2)
    expect(wrapper.find('.message-bubble--sender .message-bubble__author').text()).toBe(
      'سارة عبدالله',
    )
    expect(wrapper.find('.message-bubble--team .message-bubble__author').text()).toBe(
      'فريق منصة مؤشر',
    )
    expect(wrapper.find('.message-thread__email bdi').text()).toBe('sara@alamal.example.sa')
    expect(wrapper.find('.message-thread__meta').text()).toContain('2026-11-02')
  })

  it('sends a reply and emits the updated thread', async () => {
    contactMessagesService.reply.mockResolvedValue({ data: { ...OPEN, status: 'in-progress' } })
    const wrapper = mountThread()
    await wrapper.find('.message-thread__composer textarea').setValue('شكراً، اتضحت الصورة')
    await wrapper.find('.message-thread__composer').trigger('submit')
    await flushPromises()
    expect(contactMessagesService.reply).toHaveBeenCalledWith(OPEN.id, 'شكراً، اتضحت الصورة')
    expect(wrapper.emitted('replied')[0][0].status).toBe('in-progress')
  })

  it('shows a closed thread without a composer and offers a new message', async () => {
    const wrapper = mountThread({
      ...OPEN,
      status: 'closed',
      canReply: false,
      closedAt: '2026-11-04T08:00:00Z',
    })
    expect(wrapper.find('textarea').exists()).toBe(false)
    expect(wrapper.find('.message-thread__system').text()).toContain('2026-11-04')
    await wrapper.find('.message-thread__closed .btn').trigger('click')
    expect(wrapper.emitted('new-message')).toHaveLength(1)
  })
})

describe('MessageThreadItem', () => {
  it('links the thread with an unread marker and an isolated reference', () => {
    const wrapper = mount(MessageThreadItem, {
      props: { item: OPEN, selected: true },
      global: createTestGlobals(),
    })
    expect(wrapper.find('a').attributes('href')).toBe(`/messages/${OPEN.id}`)
    expect(wrapper.find('a').attributes('aria-current')).toBe('page')
    expect(wrapper.find('.message-item__dot').exists()).toBe(true)
    expect(wrapper.find('.message-item__reference').attributes('dir')).toBe('ltr')
  })
})
