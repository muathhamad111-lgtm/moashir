import { describe, expect, it } from 'vitest'
import ar from '@/i18n/locales/ar.json'
import en from '@/i18n/locales/en.json'
import { CYCLE_STATUSES } from '@/constants/advanced'
import { FILTERING_WINDOW, filteringWindowOf } from '@/utils/award/filteringWindow'

describe('filteringWindowOf', () => {
  it.each([
    [CYCLE_STATUSES.DRAFT, FILTERING_WINDOW.BEFORE],
    [CYCLE_STATUSES.SUBMISSION_OPEN, FILTERING_WINDOW.OPEN],
    [CYCLE_STATUSES.SUBMISSION_CLOSED, FILTERING_WINDOW.OPEN],
    [CYCLE_STATUSES.COMMITTEE_ASSESSMENT, FILTERING_WINDOW.OPEN],
    [CYCLE_STATUSES.AWAITING_SECRETARY, FILTERING_WINDOW.OPEN],
    [CYCLE_STATUSES.APPROVED, FILTERING_WINDOW.ENDED],
    [CYCLE_STATUSES.ANNOUNCED, FILTERING_WINDOW.ENDED],
    [CYCLE_STATUSES.CLOSED, FILTERING_WINDOW.ENDED],
  ])('places a %s cycle in the %s window', (status, window) => {
    expect(filteringWindowOf(status)).toBe(window)
  })

  it('treats an unknown cycle as open so the server decides', () => {
    expect(filteringWindowOf(undefined)).toBe(FILTERING_WINDOW.OPEN)
  })

  it.each([
    ['ar', ar],
    ['en', en],
  ])('words both closed windows in %s', (_locale, messages) => {
    for (const window of [FILTERING_WINDOW.BEFORE, FILTERING_WINDOW.ENDED]) {
      expect(messages.filtering.window[window].title.trim()).not.toBe('')
      expect(messages.filtering.window[window].message.trim()).not.toBe('')
    }
    expect(messages.cycles.form.thresholdLockedHint.trim()).not.toBe('')
  })
})
