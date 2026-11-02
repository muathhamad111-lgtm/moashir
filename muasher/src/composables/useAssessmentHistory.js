import { ref } from 'vue'
import { assessmentsService } from '@/services/assessmentsService'
import { usePagination } from '@/composables/usePagination'

const NEWEST_FIRST = '-created_at'

export function useAssessmentHistory({ perPage = 10 } = {}) {
  const status = ref('loading')
  const errorMessage = ref('')
  const items = ref([])
  const { pagination, setPage, applyMeta, toParams } = usePagination({ perPage })

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data, meta } = await assessmentsService.list({ sort: NEWEST_FIRST, ...toParams() })
      items.value = data
      applyMeta(meta)
      status.value = items.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  function goToPage(page) {
    setPage(page)
    return load()
  }

  return { status, errorMessage, items, pagination, load, goToPage }
}
