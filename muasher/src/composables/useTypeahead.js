import { onScopeDispose } from 'vue'
import { TYPEAHEAD_RESET_MS } from '@/constants/dropdown'
import { startsWithQuery } from '@/utils/text/normalizeArabic'

export function isTypeaheadKey(event) {
  return event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey
}

function isRepeatedCharacter(text) {
  return text.length > 1 && [...text].every((char) => char === text[0])
}

export function useTypeahead(labels) {
  let buffer = ''
  let timer = null

  function reset() {
    buffer = ''
    clearTimeout(timer)
    timer = null
  }

  function hasBuffer() {
    return buffer.length > 0
  }

  function find(search, startIndex) {
    const list = labels()
    for (let offset = 0; offset < list.length; offset += 1) {
      const index = (startIndex + offset + list.length) % list.length
      if (startsWithQuery(list[index], search)) return index
    }
    return -1
  }

  function type(char, activeIndex) {
    clearTimeout(timer)
    buffer += char
    timer = setTimeout(reset, TYPEAHEAD_RESET_MS)
    const cycling = buffer.length === 1 || isRepeatedCharacter(buffer)
    const search = cycling ? buffer[0] : buffer
    return find(search, cycling ? activeIndex + 1 : Math.max(activeIndex, 0))
  }

  onScopeDispose(reset)

  return { type, reset, hasBuffer }
}
