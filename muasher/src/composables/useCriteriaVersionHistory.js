import { computed, ref } from 'vue'
import { criteriaService } from '@/services/criteriaService'
import { useCriteriaVersions } from '@/composables/useCriteriaVersions'

// The largest page the API serves; every page is read, so no event is ever cut off.
const EVENT_PAGE_SIZE = 100
const TEXT_CORRECTION = 'text_correction'

// The criteria versions with their publication (scope, reason, publisher, changes) and their later
// text corrections, for the versions tab and the "What changed" page.
export function useCriteriaVersionHistory() {
  const versions = useCriteriaVersions()
  const events = ref([])
  const eventsFailed = ref(false)

  const status = computed(() => (eventsFailed.value ? 'error' : versions.status.value))

  // All the pages, following `meta.pagination.lastPage`. A failed page fails the whole list: a change
  // list with a silent gap would read as complete.
  async function fetchAllEvents() {
    const all = []
    for (let page = 1, lastPage = 1; page <= lastPage; page += 1) {
      const envelope = await criteriaService.listEditEvents({ perPage: EVENT_PAGE_SIZE, page })
      all.push(...(envelope.data ?? []))
      lastPage = envelope.meta?.pagination?.lastPage ?? 1
    }
    return all
  }

  async function loadEvents() {
    eventsFailed.value = false
    try {
      events.value = await fetchAllEvents()
    } catch {
      events.value = []
      eventsFailed.value = true
    }
  }

  function load() {
    return Promise.all([versions.load(), loadEvents()])
  }

  function publicationOf(versionId) {
    return (
      events.value.find(
        (event) => event.kind !== TEXT_CORRECTION && event.toVersion?.id === versionId,
      ) ?? null
    )
  }

  function correctionsOf(versionId) {
    return events.value.filter(
      (event) => event.kind === TEXT_CORRECTION && event.fromVersion?.id === versionId,
    )
  }

  return {
    versions: versions.versions,
    findVersion: versions.findVersion,
    status,
    load,
    publicationOf,
    correctionsOf,
  }
}
