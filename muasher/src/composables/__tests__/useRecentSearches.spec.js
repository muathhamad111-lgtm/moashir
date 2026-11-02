import { describe, expect, it, beforeEach } from 'vitest'
import { nextTick, ref } from 'vue'
import { isStorableSearch, useRecentSearches } from '@/composables/useRecentSearches'

beforeEach(() => localStorage.clear())

describe('isStorableSearch', () => {
  it('rejects anything that looks like personal data or is too short', () => {
    expect(isStorableSearch('جمعية البر')).toBe(true)
    expect(isStorableSearch('req-6002')).toBe(true)
    expect(isStorableSearch('ب')).toBe(false)
    expect(isStorableSearch('noura.saad@example.sa')).toBe(false)
    expect(isStorableSearch('0551234567')).toBe(false)
    expect(isStorableSearch('055 123 4567')).toBe(false)
    expect(isStorableSearch('1012345678')).toBe(false)
  })
})

describe('useRecentSearches', () => {
  it('keeps at most 5 unique recent searches per user, newest first', () => {
    const userId = ref(7001)
    const recent = useRecentSearches(userId)
    ;['البر', 'نماء', 'إحسان', 'عطاء', 'رواد', 'تمكين', 'نماء'].forEach((term) => recent.remember(term))
    expect(recent.items.value).toEqual(['نماء', 'تمكين', 'رواد', 'عطاء', 'إحسان'])
    expect(JSON.parse(localStorage.getItem('muasher.search.recent.7001'))).toHaveLength(5)
  })

  it('never stores personal data and stores nothing without a user', () => {
    const anonymous = useRecentSearches(ref(null))
    anonymous.remember('جمعية البر')
    expect(localStorage.length).toBe(0)
    const recent = useRecentSearches(ref(7001))
    recent.remember('noura.saad@example.sa')
    recent.remember('0551234567')
    expect(recent.items.value).toEqual([])
  })

  it('isolates users and clears only the current user', async () => {
    const userId = ref(7001)
    const recent = useRecentSearches(userId)
    recent.remember('البر')
    userId.value = 7002
    await nextTick()
    expect(recent.items.value).toEqual([])
    recent.remember('نماء')
    recent.clear()
    expect(localStorage.getItem('muasher.search.recent.7002')).toBeNull()
    expect(localStorage.getItem('muasher.search.recent.7001')).not.toBeNull()
  })

  it('drops tampered entries on read', () => {
    localStorage.setItem('muasher.search.recent.7001', JSON.stringify(['البر', 42, 'a@b.example.sa']))
    const recent = useRecentSearches(ref(7001))
    expect(recent.items.value).toEqual(['البر'])
  })
})
