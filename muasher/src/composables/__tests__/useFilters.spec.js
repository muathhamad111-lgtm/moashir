import { describe, expect, it } from 'vitest'
import { useFilters } from '@/composables/useFilters'

describe('useFilters', () => {
  it('builds 05-integrations list params (search, sort, filter[...])', () => {
    const { search, sort, setFilter, toParams } = useFilters({ status: '' })
    search.value = 'جمعية'
    sort.value = '-created_at'
    setFilter('status', 'under-review')
    expect(toParams()).toEqual({
      search: 'جمعية',
      sort: '-created_at',
      'filter[status]': 'under-review',
    })
  })

  it('omits empty values from the params', () => {
    const { toParams } = useFilters({ status: '', role: null })
    expect(toParams()).toEqual({})
  })

  it('resets to the initial state', () => {
    const { search, filters, setFilter, reset, toParams } = useFilters({ status: 'draft' })
    search.value = 'x'
    setFilter('status', 'completed')
    reset()
    expect(search.value).toBe('')
    expect(filters.status).toBe('draft')
    expect(toParams()).toEqual({ 'filter[status]': 'draft' })
  })
})
