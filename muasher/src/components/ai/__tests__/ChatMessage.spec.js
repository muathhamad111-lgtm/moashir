import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ChatMessage from '@/components/ai/ChatMessage.vue'
import { createTestGlobals } from '@/test-utils'

function localized(content) {
  return typeof content === 'string' ? content : content.en
}

function mountMessage(message) {
  return mount(ChatMessage, {
    props: { message, localized },
    global: { plugins: createTestGlobals({ locale: 'en' }).plugins },
  })
}

describe('ChatMessage', () => {
  it('renders a user message with the user modifier', () => {
    const wrapper = mountMessage({
      id: '1',
      role: 'user',
      kind: 'text',
      content: 'What is Muasher?',
    })
    expect(wrapper.find('.chat-message').classes()).toContain('chat-message--user')
    expect(wrapper.find('.chat-message__text').text()).toBe('What is Muasher?')
  })

  it('renders a localized assistant answer', () => {
    const wrapper = mountMessage({
      id: '2',
      role: 'assistant',
      kind: 'answer',
      content: { ar: 'إجابة', en: 'An answer' },
    })
    expect(wrapper.find('.chat-message').classes()).toContain('chat-message--assistant')
    expect(wrapper.find('.chat-message__text').text()).toBe('An answer')
  })

  it('renders the explicit KB-miss fallback', () => {
    const wrapper = mountMessage({ id: '3', role: 'assistant', kind: 'miss', content: null })
    expect(wrapper.find('.chat-message--miss').exists()).toBe(true)
    expect(wrapper.find('.chat-message__text--miss').text()).toContain(
      'available in the knowledge base',
    )
  })
})
