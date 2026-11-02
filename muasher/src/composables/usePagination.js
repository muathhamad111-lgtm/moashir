import { computed, reactive } from 'vue'

const DEFAULT_PER_PAGE = 15

export function usePagination({ page = 1, perPage = DEFAULT_PER_PAGE } = {}) {
  const state = reactive({ page, perPage, total: 0, lastPage: 1 })

  const hasPrevious = computed(() => state.page > 1)
  const hasNext = computed(() => state.page < state.lastPage)

  function setPage(next) {
    if (next < 1 || next > state.lastPage) return
    state.page = next
  }

  function setPerPage(next) {
    state.perPage = next
    state.page = 1
  }

  function applyMeta(meta) {
    const pagination = meta?.pagination
    if (!pagination) return
    state.total = pagination.total ?? state.total
    state.lastPage = pagination.lastPage ?? state.lastPage
    state.page = pagination.page ?? state.page
  }

  function toParams() {
    return { page: state.page, perPage: state.perPage }
  }

  return { pagination: state, hasPrevious, hasNext, setPage, setPerPage, applyMeta, toParams }
}
