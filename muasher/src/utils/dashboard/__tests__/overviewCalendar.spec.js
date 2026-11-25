import { describe, expect, it } from 'vitest'
import {
  isMonthOpen,
  isQuarterOpen,
  rangeLabel,
  selectableYears,
} from '@/utils/dashboard/overviewCalendar'

const TODAY = '2026-11-25'

describe('overviewCalendar', () => {
  it('opens the months from the first month of the boards up to the current one', () => {
    expect(isMonthOpen(2026, 1, TODAY)).toBe(true)
    expect(isMonthOpen(2026, 11, TODAY)).toBe(true)
    expect(isMonthOpen(2026, 12, TODAY)).toBe(false)
    expect(isMonthOpen(2025, 12, TODAY)).toBe(false)
  })

  it('opens a quarter when one of its months is open', () => {
    expect(isQuarterOpen(2026, 4, TODAY)).toBe(true)
    expect(isQuarterOpen(2027, 1, TODAY)).toBe(false)
  })

  it('lists the years from the first one to the current one', () => {
    expect(selectableYears(TODAY)).toEqual([2026])
    expect(selectableYears('2028-02-29')).toEqual([2026, 2027, 2028])
  })

  // ICU versions differ in the spaces around the range dash (thin, narrow or none), so the
  // labels are compared without their whitespace.
  it('names a range once per shared month and year, and a single day as a date', () => {
    const label = (from, to, locale) => rangeLabel(from, to, locale).replace(/\s/gu, '')
    expect(label('2026-03-12', '2026-04-04', 'ar')).toBe('12مارس–4أبريل2026')
    expect(label('2026-03-12', '2026-04-04', 'en')).toBe('March12–April4,2026')
    expect(label('2026-11-10', '2026-11-10', 'en')).toBe('November10,2026')
  })
})
