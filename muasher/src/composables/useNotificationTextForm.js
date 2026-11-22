import { computed, ref, toValue, watch } from 'vue'
import { notificationTextsService } from '@/services/notificationTextsService'
import { useVariableInsertion } from '@/composables/useVariableInsertion'

const LOCALES = ['ar', 'en']
const SMS = 'sms'
const EMPTY = Object.freeze({ ar: '', en: '' })

function editableParts(text) {
  return Object.keys(text.parts).filter((part) => !text.parts[part].isFixed)
}

function currentOf(text, part) {
  return part === SMS ? (text.sms?.current ?? EMPTY) : text.parts[part].current
}

function defaultOf(text, part) {
  return part === SMS ? EMPTY : text.parts[part].default
}

function draftOf(text, withSms) {
  const parts = withSms ? [...editableParts(text), SMS] : editableParts(text)
  return Object.fromEntries(parts.map((part) => [part, { ...currentOf(text, part) }]))
}

function differs(a, b) {
  return LOCALES.some((locale) => a[locale].trim() !== b[locale].trim())
}

function useSubmission() {
  const errors = ref({})
  const busy = ref(false)

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

  return { errors, busy, run }
}

/**
 * The draft of one notification text across its channels. Saving sends only the parts that differ from the
 * shipped wording (and the SMS once written), so an untouched channel keeps following the shipped text; a
 * core notification never sends its fixed button or an SMS.
 */
export function useNotificationTextForm(text, isCore, { onSaved, onReset }) {
  const draft = ref(draftOf(text.value, !toValue(isCore)))
  const { errors, busy, run } = useSubmission()
  const { remember, insertVariable } = useVariableInsertion(draft)

  const isDirty = computed(() =>
    Object.entries(draft.value).some(([part, value]) =>
      differs(value, currentOf(text.value, part)),
    ),
  )

  const changedParts = computed(() =>
    Object.fromEntries(
      Object.entries(draft.value).filter(([part, value]) =>
        differs(value, defaultOf(text.value, part)),
      ),
    ),
  )

  watch(text, (next) => {
    draft.value = draftOf(next, !toValue(isCore))
    errors.value = {}
  })

  function errorOf(part, locale) {
    const messages = errors.value[`parts.${part}.${locale}`] ?? errors.value[`parts.${part}`]
    return Array.isArray(messages) ? messages[0] : (messages ?? '')
  }

  function setPart(part, locale, value) {
    draft.value[part][locale] = value
  }

  function reset() {
    return run(async () => {
      await notificationTextsService.reset(text.value.key)
      onReset()
    })
  }

  function save() {
    if (!Object.keys(changedParts.value).length) return reset()
    return run(async () => {
      const { data } = await notificationTextsService.save(text.value.key, changedParts.value)
      onSaved(data)
    })
  }

  return { draft, errors, busy, isDirty, errorOf, setPart, remember, insertVariable, save, reset }
}
