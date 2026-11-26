import { onScopeDispose, readonly, ref } from 'vue'

function mediaQueryList(query) {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return null
  return window.matchMedia(query)
}

export function useMediaQuery(query) {
  const list = mediaQueryList(query)
  const matches = ref(list?.matches ?? false)
  if (!list) return readonly(matches)

  function update(event) {
    matches.value = event.matches
  }

  list.addEventListener('change', update)
  onScopeDispose(() => list.removeEventListener('change', update))

  return readonly(matches)
}
