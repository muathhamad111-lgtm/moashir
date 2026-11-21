import { computed, ref, watch } from 'vue'
import { notificationTextsService } from '@/services/notificationTextsService'
import { useVariableInsertion } from '@/composables/useVariableInsertion'

const LOCALES = ['ar', 'en']
const SMS = 'sms'
const EMPTY = Object.freeze({ ar: '', en: '' })

function currentOf(text, part) {
  return part === SMS ? (text.sms?.current ?? EMPTY) : text.parts[part].current
}

function draftOf(text, withSms) {
  const parts = Object.keys(text.parts)
  const all = withSms || text.sms?.current ? [...parts, SMS] : parts
  return Object.fromEntries(all.map((part) => [part, { ...currentOf(text, part) }]))
}

export function useNotificationTextForm(text, smsEnabled, { onSaved, onReset }) {
  const draft = ref(draftOf(text.value, smsEnabled.value))
  const errors = ref({})
  const busy = ref(false)
  const { remember, insertVariable } = useVariableInsertion(draft)

  const isDirty = computed(() =>
    Object.entries(draft.value).some(([part, value]) =>
      LOCALES.some((locale) => value[locale] !== currentOf(text.value, part)[locale]),
    ),
  )

  watch([text, smsEnabled], ([next, withSms]) => {
    draft.value = draftOf(next, withSms)
    errors.value = {}
  })

  function errorOf(part, locale) {
    const messages = errors.value[`parts.${part}.${locale}`] ?? errors.value.parts
    return Array.isArray(messages) ? messages[0] : (messages ?? '')
  }

  async function run(action) {
    busy.value = true
    errors.value = {}
    try {
      return await action()
    } catch (error) {
      errors.value = error?.normalized?.fieldErrors ?? {}
      throw error
    } finally {
      busy.value = false
    }
  }

  function save() {
    return run(async () => {
      const { data } = await notificationTextsService.save(text.value.key, draft.value)
      onSaved(data)
    })
  }

  function reset() {
    return run(async () => {
      await notificationTextsService.reset(text.value.key)
      onReset()
    })
  }

  return { draft, errors, busy, isDirty, errorOf, remember, insertVariable, save, reset }
}
