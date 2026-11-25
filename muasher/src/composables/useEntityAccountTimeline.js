import { computed, ref } from 'vue'
import { entitiesService } from '@/services/entitiesService'
import { usePagination } from '@/composables/usePagination'
import { useTimelineEntryFormat } from '@/composables/useTimelineEntryFormat'

const PER_PAGE = 15

/**
 * The staff record's account timeline, a page at a time from GET /entities/{entity}/timeline: lifecycle
 * events, profile changes and decisions, and e-mail changes, newest first.
 */
export function useEntityAccountTimeline(entityId) {
  const { itemOf } = useTimelineEntryFormat()
  const paginator = usePagination({ perPage: PER_PAGE })
  const status = ref('loading')
  const entries = ref([])

  async function load() {
    status.value = 'loading'
    try {
      const envelope = await entitiesService.timeline(entityId.value, paginator.toParams())
      entries.value = envelope.data ?? []
      paginator.applyMeta(envelope.meta)
      status.value = entries.value.length ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  function goTo(page) {
    paginator.setPage(page)
    return load()
  }

  const items = computed(() => entries.value.map(itemOf))

  return { status, items, pagination: paginator.pagination, load, goTo }
}
