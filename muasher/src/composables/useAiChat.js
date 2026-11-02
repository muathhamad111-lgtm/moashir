import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { aiAssistantService } from '@/services/aiAssistantService'

let messageSeed = 0
function nextMessageId() {
  messageSeed += 1
  return `msg-${messageSeed}`
}

function resolve(content, locale) {
  if (content === null || content === undefined) return ''
  if (typeof content === 'string') return content
  return content[locale] || content.ar || content.en || ''
}

export function useAiChat({ auto = true } = {}) {
  const { locale } = useI18n()
  const availability = ref('loading')
  const messages = ref([])
  const sending = ref(false)
  const lastError = ref(false)
  let lastQuestion = ''

  function localized(value) {
    return resolve(value, locale.value)
  }

  function pushMessage(message) {
    messages.value.push({ id: nextMessageId(), ts: new Date().toISOString(), ...message })
  }

  async function loadAvailability() {
    availability.value = 'loading'
    try {
      const { data } = await aiAssistantService.getAssistant()
      availability.value = data?.active ? 'active' : 'inactive'
    } catch {
      availability.value = 'error'
    }
  }

  async function runQuery(question) {
    sending.value = true
    lastError.value = false
    try {
      const { data } = await aiAssistantService.query(question)
      if (data?.matched) {
        pushMessage({ role: 'assistant', kind: 'answer', content: data.answer })
      } else {
        pushMessage({ role: 'assistant', kind: 'miss', content: null })
      }
    } catch {
      lastError.value = true
    } finally {
      sending.value = false
    }
  }

  async function send(question) {
    const trimmed = String(question ?? '').trim()
    if (!trimmed || sending.value || availability.value !== 'active') return
    lastQuestion = trimmed
    pushMessage({ role: 'user', kind: 'text', content: trimmed })
    await runQuery(trimmed)
  }

  async function retry() {
    if (!lastQuestion || sending.value) return
    await runQuery(lastQuestion)
  }

  if (auto) onMounted(loadAvailability)

  return {
    availability,
    messages,
    sending,
    lastError,
    localized,
    send,
    retry,
    loadAvailability,
  }
}
