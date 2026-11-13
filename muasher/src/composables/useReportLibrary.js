import { computed, ref, unref } from 'vue'
import { insightsService } from '@/services/insightsService'

export function useReportLibrary(entityId = null) {
  const status = ref('loading')
  const rows = ref([])
  const comingSoon = ref([])
  const pagination = ref(null)
  const loadingMore = ref(false)
  const moreFailed = ref(false)
  const hasMore = computed(
    () => Boolean(pagination.value) && pagination.value.page < pagination.value.lastPage,
  )

  async function fetchPage(page) {
    const { data, meta } = await insightsService.library(unref(entityId), { page })
    comingSoon.value = meta?.comingSoon ?? []
    pagination.value = meta?.pagination ?? null
    return data
  }

  async function load() {
    status.value = 'loading'
    try {
      rows.value = await fetchPage(1)
      status.value = 'ready'
    } catch {
      status.value = 'error'
    }
  }

  async function loadMore() {
    if (!hasMore.value || loadingMore.value) return
    loadingMore.value = true
    moreFailed.value = false
    try {
      rows.value = [...rows.value, ...(await fetchPage(pagination.value.page + 1))]
    } catch {
      moreFailed.value = true
    } finally {
      loadingMore.value = false
    }
  }

  return { status, rows, comingSoon, hasMore, loadingMore, moreFailed, load, loadMore }
}
