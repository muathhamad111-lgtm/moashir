import { ref } from 'vue'
import { assessmentsService } from '@/services/assessmentsService'
import { usePagination } from '@/composables/usePagination'

const PER_PAGE = 15

// Every assessment request of one entity, newest first, a page at a time.
export function useEntityAssessments(entityId) {
  const paginator = usePagination({ perPage: PER_PAGE })
  const status = ref('loading')
  const items = ref([])

  async function load() {
    status.value = 'loading'
    try {
      const envelope = await assessmentsService.list({
        'filter[entityId]': entityId.value,
        sort: '-created_at',
        ...paginator.toParams(),
      })
      items.value = envelope.data
      paginator.applyMeta(envelope.meta)
      status.value = items.value.length ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  function goTo(page) {
    paginator.setPage(page)
    return load()
  }

  return { status, items, pagination: paginator.pagination, load, goTo }
}
