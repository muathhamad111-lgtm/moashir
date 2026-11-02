import { describe, expect, it, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { mount } from '@vue/test-utils'
import ChatWidget from '@/components/ai/ChatWidget.vue'
import { createTestGlobals } from '@/test-utils'

const chat = {
  availability: ref('active'),
  messages: ref([]),
  sending: ref(false),
  lastError: ref(false),
  send: vi.fn(),
  retry: vi.fn(),
}

vi.mock('@/composables/useAiChat', () => ({
  useAiChat: () => ({
    availability: chat.availability,
    messages: chat.messages,
    sending: chat.sending,
    lastError: chat.lastError,
    localized: (content) => (typeof content === 'string' ? content : content.en),
    send: chat.send,
    retry: chat.retry,
  }),
}))

function mountWidget() {
  return mount(ChatWidget, {
    attachTo: document.body,
    global: { plugins: createTestGlobals({ locale: 'en' }).plugins },
  })
}

beforeEach(() => {
  chat.availability.value = 'active'
  chat.messages.value = []
  chat.sending.value = false
  chat.lastError.value = false
  chat.send.mockClear()
  chat.retry.mockClear()
})

describe('ChatWidget', () => {
  it('is closed by default and opens the dialog panel on launcher click', async () => {
    const wrapper = mountWidget()
    expect(wrapper.find('.chat-widget__panel').exists()).toBe(false)
    await wrapper.find('.chat-widget__launcher').trigger('click')
    const panel = wrapper.find('.chat-widget__panel')
    expect(panel.exists()).toBe(true)
    expect(panel.attributes('role')).toBe('dialog')
  })

  it('shows the welcome suggestions when there are no messages', async () => {
    const wrapper = mountWidget()
    await wrapper.find('.chat-widget__launcher').trigger('click')
    expect(wrapper.find('.chat-widget__welcome').exists()).toBe(true)
    expect(wrapper.findAll('.chat-widget__suggestion')).toHaveLength(4)
  })

  it('sends a typed question and clears the input', async () => {
    const wrapper = mountWidget()
    await wrapper.find('.chat-widget__launcher').trigger('click')
    const input = wrapper.find('.chat-widget__input')
    await input.setValue('What is Muasher?')
    await wrapper.find('.chat-widget__composer').trigger('submit')
    expect(chat.send).toHaveBeenCalledWith('What is Muasher?')
    expect(input.element.value).toBe('')
  })

  it('does not send an empty/whitespace question', async () => {
    const wrapper = mountWidget()
    await wrapper.find('.chat-widget__launcher').trigger('click')
    await wrapper.find('.chat-widget__input').setValue('   ')
    await wrapper.find('.chat-widget__composer').trigger('submit')
    expect(chat.send).not.toHaveBeenCalled()
  })

  it('renders the messages in an aria-live region including the KB-miss fallback', async () => {
    chat.messages.value = [
      { id: 'm1', role: 'user', kind: 'text', content: 'x' },
      { id: 'm2', role: 'assistant', kind: 'miss', content: null },
    ]
    const wrapper = mountWidget()
    await wrapper.find('.chat-widget__launcher').trigger('click')
    const list = wrapper.find('.chat-widget__messages')
    expect(list.attributes('aria-live')).toBe('polite')
    expect(wrapper.find('.chat-message--miss').exists()).toBe(true)
  })

  it('shows the typing indicator and disables input while sending', async () => {
    chat.sending.value = true
    const wrapper = mountWidget()
    await wrapper.find('.chat-widget__launcher').trigger('click')
    expect(wrapper.find('.chat-widget__typing').exists()).toBe(true)
    expect(wrapper.find('.chat-widget__input').attributes('disabled')).toBeDefined()
  })

  it('shows a per-turn error with a retry that calls retry()', async () => {
    chat.lastError.value = true
    const wrapper = mountWidget()
    await wrapper.find('.chat-widget__launcher').trigger('click')
    const retry = wrapper.find('.chat-widget__turn-error .chat-widget__inline-retry')
    expect(retry.exists()).toBe(true)
    await retry.trigger('click')
    expect(chat.retry).toHaveBeenCalled()
  })

  it('shows the inactive state and disables the composer when the assistant is off', async () => {
    chat.availability.value = 'inactive'
    const wrapper = mountWidget()
    await wrapper.find('.chat-widget__launcher').trigger('click')
    expect(wrapper.find('.chat-widget__status--inactive').exists()).toBe(true)
    expect(wrapper.find('.chat-widget__input').attributes('disabled')).toBeDefined()
  })

  it('closes on Escape', async () => {
    const wrapper = mountWidget()
    await wrapper.find('.chat-widget__launcher').trigger('click')
    expect(wrapper.find('.chat-widget__panel').exists()).toBe(true)
    await wrapper.find('.chat-widget__panel').trigger('keydown', { key: 'Escape' })
    expect(wrapper.find('.chat-widget__panel').exists()).toBe(false)
  })
})
