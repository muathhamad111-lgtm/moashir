import { ref, unref } from 'vue'
import { insightsService } from '@/services/insightsService'

const MAX_RESULT_PAGES = 10

async function allResults(entityId) {
  const results = []
  for (let page = 1; page <= MAX_RESULT_PAGES; page += 1) {
    const { data, meta } = await insightsService.results(entityId, { page })
    results.push(...data)
    if (page >= (meta?.pagination?.lastPage ?? 1)) break
  }
  return results
}

export function useInsightsOverview(entityId = null) {
  const status = ref('loading')
  const overview = ref(null)
  const results = ref([])

  async function load() {
    status.value = 'loading'
    try {
      const id = unref(entityId)
      const [summary, list] = await Promise.all([insightsService.overview(id), allResults(id)])
      overview.value = summary.data
      results.value = list
      status.value = 'ready'
    } catch {
      status.value = 'error'
    }
  }

  return { status, overview, results, load }
}
