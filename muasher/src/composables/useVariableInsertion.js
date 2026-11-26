import { ref } from 'vue'

const ENDS_WITH_TEXT = /\S$/

function insertAt(value, name, field) {
  const start = field?.selectionStart ?? value.length
  const end = field?.selectionEnd ?? value.length
  const before = value.slice(0, start)
  const token = `${ENDS_WITH_TEXT.test(before) ? ' ' : ''}:${name}`
  return { text: `${before}${token}${value.slice(end)}`, caret: start + token.length }
}

export function useVariableInsertion(draft) {
  const focused = ref(null)

  function remember(part, locale, event) {
    focused.value = { part, locale, field: event.target }
  }

  function insertVariable(name) {
    const target = focused.value ?? { part: Object.keys(draft.value)[0], locale: 'ar', field: null }
    const current = draft.value[target.part][target.locale]
    const { text, caret } = insertAt(current, name, target.field)
    draft.value[target.part][target.locale] = text
    target.field?.focus()
    requestAnimationFrame(() => target.field?.setSelectionRange(caret, caret))
  }

  return { remember, insertVariable }
}
