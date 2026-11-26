import { describe, expect, it } from 'vitest'
import {
  isIncomplete,
  isoOf,
  segmentsFromText,
  segmentsOf,
  spillDigits,
} from '@/utils/dateSegments'

describe('dateSegments', () => {
  it('splits an ISO date and ignores anything else', () => {
    expect(segmentsOf('2015-03-21')).toEqual({ day: '21', month: '03', year: '2015' })
    expect(segmentsOf('21/03/2015')).toEqual({ day: '', month: '', year: '' })
    expect(segmentsOf(null)).toEqual({ day: '', month: '', year: '' })
  })

  it('builds the ISO date only for a real calendar day', () => {
    expect(isoOf({ day: '7', month: '3', year: '2015' })).toBe('2015-03-07')
    expect(isoOf({ day: '29', month: '02', year: '2024' })).toBe('2024-02-29')
    expect(isoOf({ day: '29', month: '02', year: '2025' })).toBe('')
    expect(isoOf({ day: '21', month: '13', year: '2015' })).toBe('')
    expect(isoOf({ day: '21', month: '03', year: '201' })).toBe('')
  })

  it('tells an incomplete date from an empty one', () => {
    expect(isIncomplete({ day: '', month: '', year: '' })).toBe(false)
    expect(isIncomplete({ day: '21', month: '', year: '' })).toBe(true)
    expect(isIncomplete({ day: '21', month: '03', year: '2015' })).toBe(false)
  })

  it('reads typed or pasted dates in day, month, year order or ISO, with any digits', () => {
    expect(segmentsFromText('٢١/٣/٢٠١٥')).toEqual({ day: '21', month: '03', year: '2015' })
    expect(segmentsFromText('1-12-2011')).toEqual({ day: '01', month: '12', year: '2011' })
    expect(segmentsFromText('2011-12-01')).toEqual({ day: '01', month: '12', year: '2011' })
    expect(segmentsFromText('next Sunday')).toBeNull()
  })

  it('spills digits beyond a box into the following boxes', () => {
    const empty = { day: '', month: '', year: '' }

    expect(spillDigits(empty, 0, '2')).toEqual({ segments: { ...empty, day: '2' }, lastIndex: 0 })
    expect(spillDigits(empty, 0, '٠٧١٢٢٠١١٩')).toEqual({
      segments: { day: '07', month: '12', year: '2011' },
      lastIndex: 2,
    })
    expect(spillDigits({ day: '07', month: '', year: '' }, 1, '1a2')).toEqual({
      segments: { day: '07', month: '12', year: '' },
      lastIndex: 1,
    })
  })
})
