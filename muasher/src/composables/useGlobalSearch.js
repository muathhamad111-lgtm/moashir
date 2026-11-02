import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { searchService } from '@/services/searchService'
import { SEARCH_DEBOUNCE_MS, SEARCH_MIN_LENGTH } from '@/constants/globalSearch'

export function useGlobalSearch({ debounceMs = SEARCH_DEBOUNCE_MS } = {}) {
  const query = ref('')
  const groups = ref([])
  const status = ref('idle')
  let timer = null
  let sequence = 0

  const term = computed(() => query.value.trim())
  const options = computed(() => groups.value.flatMap((group) => group.items))

  function settle(nextStatus) {
    sequence += 1
    clearTimeout(timer)
    groups.value = []
    status.value = nextStatus
  }

  async function run(text) {
    const ticket = ++sequence
    status.value = 'loading'
    try {
      const { data } = await searchService.search(text)
      if (ticket !== sequence) return
      groups.value = data.groups.filter((group) => group.items.length)
      status.value = groups.value.length ? 'success' : 'empty'
    } catch {
      if (ticket !== sequence) return
      groups.value = []
      status.value = 'error'
    }
  }

  function schedule() {
    const text = term.value
    if (!text) return settle('idle')
    if (text.length < SEARCH_MIN_LENGTH) return settle('too-short')
    sequence += 1
    clearTimeout(timer)
    status.value = 'loading'
    timer = setTimeout(() => run(text), debounceMs)
  }

  function retry() {
    if (term.value.length >= SEARCH_MIN_LENGTH) run(term.value)
  }

  function clear() {
    query.value = ''
  }

  watch(term, schedule)
  onBeforeUnmount(() => clearTimeout(timer))

  return { query, term, groups, options, status, retry, clear }
}
