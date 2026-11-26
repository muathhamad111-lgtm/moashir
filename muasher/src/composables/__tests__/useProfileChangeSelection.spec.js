import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { useProfileChangeSelection } from '@/composables/useProfileChangeSelection'

function rows(...items) {
  return ref(items.map(([item, isStale = false]) => ({ item, isStale })))
}

describe('useProfileChangeSelection', () => {
  it('approves every fresh item and leaves stale ones rejected', () => {
    const selection = useProfileChangeSelection(rows(['employees_count'], ['website_url', true]))
    selection.reset()
    expect(selection.approvedItems.value).toEqual(['employees_count'])
    expect(selection.rejectedCount.value).toBe(1)
  })

  it('never approves a stale item', () => {
    const selection = useProfileChangeSelection(rows(['website_url', true]))
    selection.reset()
    selection.setApproved('website_url', true)
    expect(selection.approvedItems.value).toEqual([])
  })

  it('moves coupled items together', () => {
    const selection = useProfileChangeSelection(rows(['region_id'], ['city_id'], ['org_size']))
    selection.reset()
    selection.setApproved('city_id', false)
    expect(selection.approvedItems.value).toEqual(['org_size'])
    selection.setApproved('region_id', true)
    expect(selection.approvedItems.value).toEqual(['region_id', 'city_id', 'org_size'])
  })

  it('keeps a coupled pair rejected when one of them is stale', () => {
    const selection = useProfileChangeSelection(rows(['vat_registered'], ['vat_number', true]))
    selection.reset()
    selection.setAll(true)
    expect(selection.approvedItems.value).toEqual([])
  })

  it('rejects everything at once', () => {
    const selection = useProfileChangeSelection(rows(['employees_count'], ['logo']))
    selection.reset()
    selection.setAll(false)
    expect(selection.rejectedCount.value).toBe(2)
  })
})
