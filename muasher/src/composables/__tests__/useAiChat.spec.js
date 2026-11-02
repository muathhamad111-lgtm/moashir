import { describe, expect, it, vi, beforeEach } from 'vitest'
import { defineComponent } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { useAiChat } from '@/composables/useAiChat'
import { aiAssistantService } from '@/services/aiAssistantService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/aiAssistantService', () => ({
  aiAssistantService: { getAssistant: vi.fn(), query: vi.fn() },
}))

function useChatIn(setup) {
  let api
  const Comp = defineComponent({
    setup() {
      api = useAiChat({ auto: false })
      setup?.(api)
      return () => null
    },
  })
  const wrapper = mount(Comp, { global: { plugins: createTestGlobals({ locale: 'en' }).plugins } })
  return { api, wrapper }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useAiChat', () => {
  it('reports availability as active when the assistant is on', async () => {
    aiAssistantService.getAssistant.mockResolvedValue({ data: { active: true } })
    const { api } = useChatIn()
    await api.loadAvailability()
    expect(api.availability.value).toBe('active')
  })

  it('reports inactive when the assistant is off', async () => {
    aiAssistantService.getAssistant.mockResolvedValue({ data: { active: false } })
    const { api } = useChatIn()
    await api.loadAvailability()
    expect(api.availability.value).toBe('inactive')
  })

  it('pushes the user question then a KB answer', async () => {
    aiAssistantService.getAssistant.mockResolvedValue({ data: { active: true } })
    aiAssistantService.query.mockResolvedValue({
      data: { matched: true, answer: { ar: 'إجابة', en: 'An answer' } },
    })
    const { api } = useChatIn()
    await api.loadAvailability()
    await api.send('What is Muasher?')
    await flushPromises()
    expect(api.messages.value).toHaveLength(2)
    expect(api.messages.value[0]).toMatchObject({ role: 'user', content: 'What is Muasher?' })
    expect(api.messages.value[1]).toMatchObject({ role: 'assistant', kind: 'answer' })
  })

  it('pushes a KB-miss message when nothing matches', async () => {
    aiAssistantService.getAssistant.mockResolvedValue({ data: { active: true } })
    aiAssistantService.query.mockResolvedValue({ data: { matched: false, answer: null } })
    const { api } = useChatIn()
    await api.loadAvailability()
    await api.send('unknown question')
    await flushPromises()
    expect(api.messages.value.at(-1)).toMatchObject({ role: 'assistant', kind: 'miss' })
  })

  it('sets lastError on failure and retry re-runs the last question', async () => {
    aiAssistantService.getAssistant.mockResolvedValue({ data: { active: true } })
    aiAssistantService.query.mockRejectedValueOnce(new Error('boom'))
    const { api } = useChatIn()
    await api.loadAvailability()
    await api.send('a question')
    await flushPromises()
    expect(api.lastError.value).toBe(true)
    expect(api.messages.value).toHaveLength(1)

    aiAssistantService.query.mockResolvedValueOnce({
      data: { matched: true, answer: { ar: 'ن', en: 'ok' } },
    })
    await api.retry()
    await flushPromises()
    expect(api.lastError.value).toBe(false)
    expect(api.messages.value.at(-1)).toMatchObject({ kind: 'answer' })
  })

  it('refuses to send when the assistant is not active', async () => {
    aiAssistantService.getAssistant.mockResolvedValue({ data: { active: false } })
    const { api } = useChatIn()
    await api.loadAvailability()
    await api.send('a question')
    expect(aiAssistantService.query).not.toHaveBeenCalled()
    expect(api.messages.value).toHaveLength(0)
  })
})
