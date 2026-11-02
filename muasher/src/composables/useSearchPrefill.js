import { watch } from 'vue'
import { useSearchHandoffStore } from '@/stores/searchHandoff'

export function useSearchPrefill(search, routeName) {
  const handoff = useSearchHandoffStore()

  function apply() {
    const text = handoff.consume(routeName)
    if (text) search.value = text
  }

  apply()
  watch(
    () => handoff.pending[routeName],
    (text) => {
      if (text) apply()
    },
  )
}
