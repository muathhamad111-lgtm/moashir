import { describe, expect, it } from 'vitest'
import { formatDate, formatRelativeTime } from '@/utils/datetime'

const now = new Date('2026-07-07T12:00:00+03:00')

describe('datetime utils', () => {
  it('formats relative time in Arabic', () => {
    expect(formatRelativeTime('2026-07-07T10:00:00+03:00', 'ar', now)).toContain('ساعت')
  })

  it('formats relative time in English across units', () => {
    expect(formatRelativeTime('2026-07-07T11:59:30+03:00', 'en', now)).toContain('second')
    expect(formatRelativeTime('2026-07-06T12:00:00+03:00', 'en', now)).toBe('yesterday')
    expect(formatRelativeTime('2026-06-07T12:00:00+03:00', 'en', now)).toBe('last month')
  })

  it('formats dates per locale', () => {
    expect(formatDate('2026-07-07', 'en')).toContain('2026')
    expect(formatDate('2026-07-07', 'ar').length).toBeGreaterThan(0)
  })
})
