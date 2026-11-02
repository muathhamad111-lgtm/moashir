import { ref } from 'vue'
import { assessmentsService } from '@/services/assessmentsService'

const RECENT_LIMIT = 10

export function useEntityAssessments(entityId) {
  const status = ref('loading')
  const items = ref([])
  const total = ref(0)

  async function load() {
    status.value = 'loading'
    try {
      const { data, meta } = await assessmentsService.list({
        'filter[entityId]': entityId.value,
        sort: '-created_at',
        perPage: RECENT_LIMIT,
      })
      items.value = data
      total.value = meta?.pagination?.total ?? data.length
      status.value = data.length ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  return { status, items, total, load }
}
