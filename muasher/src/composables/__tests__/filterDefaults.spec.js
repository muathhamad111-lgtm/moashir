import { describe, expect, it } from 'vitest'
import { useFilters } from '@/composables/useFilters'
import { useFilterDraft } from '@/composables/useFilterDraft'

// A list that opens on purpose with a status (the pending requests) shows no active filter until the
// viewer changes it; «مسح الفلترة» then brings back that default view.
describe('a list default view', () => {
  it('does not count the default status as a filter the viewer applied', () => {
    const filters = useFilters({ status: 'pending' }, { defaults: { status: 'pending' } })
    const draft = useFilterDraft(filters)
    expect(draft.activeCount.value).toBe(0)
    expect(filters.isAtDefaults.value).toBe(true)

    filters.setFilter('status', 'rejected')
    expect(draft.activeCount.value).toBe(1)
    expect(filters.isAtDefaults.value).toBe(false)

    draft.clear()
    expect(filters.filters.status).toBe('pending')
    expect(draft.activeCount.value).toBe(0)
  })

  it('still counts a value that is not a default, such as one opened from a link', () => {
    const filters = useFilters({ status: 'paid' })
    expect(useFilterDraft(filters).activeCount.value).toBe(1)
  })
})
