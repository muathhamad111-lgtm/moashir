import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useFilters } from '@/composables/useFilters'
import { usePagination } from '@/composables/usePagination'
import { useTable } from '@/composables/useTable'
import { contactInboxService } from '@/services/contactInboxService'
import {
  CONTACT_INBOX_LIMITS,
  CONTACT_INBOX_STATUSES,
  CONTACT_STATUS_QUERY,
} from '@/constants/contactInbox'

const EMPTY_FILTERS = { status: '', enquiryType: '', from: '', to: '', linked: '', assignee: '' }

function statusFromQuery(value) {
  return typeof value === 'string' && CONTACT_INBOX_STATUSES.includes(value) ? value : ''
}

export function useContactInbox() {
  const route = useRoute()
  const filters = useFilters({
    ...EMPTY_FILTERS,
    status: statusFromQuery(route.query[CONTACT_STATUS_QUERY]),
  })
  const paginator = usePagination({ perPage: CONTACT_INBOX_LIMITS.perPage })
  const stats = ref(null)
  const searchInput = ref('')
  let searchTimer = null

  async function fetcher(params) {
    const envelope = await contactInboxService.list(params)
    stats.value = envelope.meta.stats
    return envelope
  }

  const table = useTable({ fetcher, filters, pagination: paginator })

  function setFilter(key, value) {
    filters.setFilter(key, value)
    paginator.setPage(1)
  }

  function applySearch(value) {
    const term = value.trim()
    if (term && term.length < CONTACT_INBOX_LIMITS.searchMin) return
    filters.search.value = term
    paginator.setPage(1)
  }

  watch(searchInput, (value) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => applySearch(value), CONTACT_INBOX_LIMITS.searchDelayMs)
  })

  watch(
    () => route.query[CONTACT_STATUS_QUERY],
    (value) => setFilter('status', statusFromQuery(value)),
  )

  function reset() {
    searchInput.value = ''
    filters.reset()
    Object.entries(EMPTY_FILTERS).forEach(([key, value]) => filters.setFilter(key, value))
    paginator.setPage(1)
  }

  const isFiltered = computed(
    () => Boolean(filters.search.value) || Object.values(filters.filters).some(Boolean),
  )

  return {
    filters: filters.filters,
    searchInput,
    paginator,
    stats,
    isFiltered,
    setFilter,
    reset,
    ...table,
  }
}
