import { describe, expect, it } from 'vitest'
import {
  addDays,
  addMonths,
  clampToRange,
  endOfWeek,
  isCalendarIso,
  isOutsideRange,
  lastOfMonth,
  monthGrid,
  startOfWeek,
} from '@/utils/calendarMonth'

describe('calendarMonth', () => {
  it('lays a month out in six Sunday-first weeks, with the next month muted at the end', () => {
    const weeks = monthGrid('2026-11-23')

    expect(weeks).toHaveLength(6)
    expect(weeks.every((week) => week.length === 7)).toBe(true)
    expect(weeks[0][0]).toBe('2026-11-01')
    expect(weeks[4]).toEqual([
      '2026-11-29',
      '2026-11-30',
      '2026-12-01',
      '2026-12-02',
      '2026-12-03',
      '2026-12-04',
      '2026-12-05',
    ])
    expect(monthGrid('2026-02-10')[0][0]).toBe('2026-02-01')
    expect(monthGrid('2026-03-10')[0][0]).toBe('2026-03-01')
    expect(monthGrid('2026-04-10')[0][0]).toBe('2026-03-29')
  })

  it('moves by days across months and years', () => {
    expect(addDays('2026-11-30', 1)).toBe('2026-12-01')
    expect(addDays('2026-01-01', -1)).toBe('2025-12-31')
    expect(addDays('2024-02-28', 1)).toBe('2024-02-29')
  })

  it('moves by months and keeps the day inside the shorter month', () => {
    expect(addMonths('2026-01-31', 1)).toBe('2026-02-28')
    expect(addMonths('2024-03-31', -1)).toBe('2024-02-29')
    expect(addMonths('2026-12-15', 1)).toBe('2027-01-15')
    expect(addMonths('2026-11-23', -12)).toBe('2025-11-23')
    expect(lastOfMonth('2026-02-03')).toBe('2026-02-28')
  })

  it('finds the start and end of a Sunday-first week', () => {
    expect(startOfWeek('2026-11-25')).toBe('2026-11-22')
    expect(endOfWeek('2026-11-25')).toBe('2026-11-28')
    expect(startOfWeek('2026-11-22')).toBe('2026-11-22')
  })

  it('keeps old years intact', () => {
    expect(addDays('0099-12-31', 1)).toBe('0100-01-01')
    expect(monthGrid('1900-01-15')[0][0]).toBe('1899-12-31')
  })

  it('recognises only real calendar dates', () => {
    expect(isCalendarIso('2026-02-28')).toBe(true)
    expect(isCalendarIso('2026-02-30')).toBe(false)
    expect(isCalendarIso('2026-2-3')).toBe(false)
    expect(isCalendarIso('')).toBe(false)
    expect(isCalendarIso(null)).toBe(false)
  })

  it('keeps a date inside the optional bounds', () => {
    const range = { min: '2026-11-10', max: '2026-11-20' }

    expect(clampToRange('2026-11-01', range)).toBe('2026-11-10')
    expect(clampToRange('2026-12-01', range)).toBe('2026-11-20')
    expect(clampToRange('2026-11-15', range)).toBe('2026-11-15')
    expect(clampToRange('2026-11-15', { min: '', max: '' })).toBe('2026-11-15')
    expect(isOutsideRange('2026-11-09', range)).toBe(true)
    expect(isOutsideRange('2026-11-20', range)).toBe(false)
    expect(isOutsideRange('2026-11-21', range)).toBe(true)
  })
})
