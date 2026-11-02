import { onMounted, ref, unref } from 'vue'
import { dashboardService } from '@/services/dashboardService'

const MAX_ATTEMPTS = 2

async function fetchSummary(key) {
  let lastError = null
  for (let attempt = 0; attempt < MAX_ATTEMPTS; attempt += 1) {
    try {
      return await dashboardService.getSummary(key)
    } catch (error) {
      lastError = error
    }
  }
  throw lastError
}

function tagActions(key, actions = []) {
  return actions.map((action) => ({ ...action, id: `${key}:${action.id}`, source: key }))
}

export function useAggregatedActions(keysSource, { auto = true } = {}) {
  const actions = ref([])

  async function load() {
    const keys = unref(keysSource)
    if (!keys.length) {
      actions.value = []
      return
    }
    const results = await Promise.allSettled(keys.map((key) => fetchSummary(key)))
    actions.value = results.flatMap((result, index) =>
      result.status === 'fulfilled' ? tagActions(keys[index], result.value.data?.actions) : [],
    )
  }

  if (auto) onMounted(load)

  return { actions, load }
}
