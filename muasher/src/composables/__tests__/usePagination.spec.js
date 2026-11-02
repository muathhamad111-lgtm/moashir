import { describe, expect, it } from 'vitest'
import { usePagination } from '@/composables/usePagination'

describe('usePagination', () => {
  it('starts at page 1 with the default per-page and builds list params', () => {
    const { pagination, toParams } = usePagination()
    expect(pagination.page).toBe(1)
    expect(toParams()).toEqual({ page: 1, perPage: 15 })
  })

  it('applies the envelope meta.pagination shape', () => {
    const { pagination, applyMeta, hasNext, hasPrevious } = usePagination()
    applyMeta({ pagination: { page: 2, perPage: 15, total: 45, lastPage: 3 } })
    expect(pagination.total).toBe(45)
    expect(pagination.lastPage).toBe(3)
    expect(pagination.page).toBe(2)
    expect(hasNext.value).toBe(true)
    expect(hasPrevious.value).toBe(true)
  })

  it('guards page bounds and resets to page 1 when per-page changes', () => {
    const { pagination, applyMeta, setPage, setPerPage } = usePagination()
    applyMeta({ pagination: { page: 1, perPage: 15, total: 30, lastPage: 2 } })
    setPage(5)
    expect(pagination.page).toBe(1)
    setPage(2)
    expect(pagination.page).toBe(2)
    setPerPage(50)
    expect(pagination.page).toBe(1)
    expect(pagination.perPage).toBe(50)
  })
})
