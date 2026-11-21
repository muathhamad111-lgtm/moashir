import { afterEach, describe, expect, it, vi } from 'vitest'
import { STORAGE_KEYS } from '@/constants/storageKeys'
import { logger } from '@/utils/logger'
import { forgetRecentSearches, recentSearchesKey } from '@/utils/recentSearchesStorage'

afterEach(() => {
  vi.restoreAllMocks()
  localStorage.clear()
})

describe('recentSearchesStorage', () => {
  it('builds one storage key per user', () => {
    expect(recentSearchesKey(7)).toBe(`${STORAGE_KEYS.recentSearches}.7`)
  })

  it('removes the recent searches of every user and nothing else', () => {
    localStorage.setItem(recentSearchesKey(7), '["Al-Amal"]')
    localStorage.setItem(recentSearchesKey(8), '["Al-Noor"]')
    localStorage.setItem(STORAGE_KEYS.theme, 'dark')
    localStorage.setItem(STORAGE_KEYS.sideNavCollapsed, 'true')
    forgetRecentSearches()
    expect(Object.keys({ ...localStorage }).sort()).toEqual(
      [STORAGE_KEYS.sideNavCollapsed, STORAGE_KEYS.theme].sort(),
    )
  })

  it('reports a storage failure instead of throwing', () => {
    const failure = new Error('storage blocked')
    vi.spyOn(Storage.prototype, 'key').mockImplementation(() => {
      throw failure
    })
    localStorage.setItem(recentSearchesKey(7), '["Al-Amal"]')
    const report = vi.spyOn(logger, 'error').mockImplementation(() => {})
    expect(() => forgetRecentSearches()).not.toThrow()
    expect(report).toHaveBeenCalledWith(failure, { action: 'search.recent.forget' })
  })
})
