import { describe, expect, it } from 'vitest'
import { matchesQuery, normalizeArabic, startsWithQuery } from '@/utils/text/normalizeArabic'

describe('normalizeArabic', () => {
  it('unifies hamza forms, ta marbuta, alef maqsura and removes diacritics and tatweel', () => {
    expect(normalizeArabic('أحمد')).toBe(normalizeArabic('احمد'))
    expect(normalizeArabic('إدارة')).toBe('اداره')
    expect(normalizeArabic('مُقيّـــم')).toBe('مقيم')
    expect(normalizeArabic('مستشفى')).toBe('مستشفي')
    expect(normalizeArabic('مؤسسة')).toBe('موسسه')
    expect(normalizeArabic('رئيس')).toBe('رييس')
  })

  it('lowercases Latin text', () => {
    expect(normalizeArabic('  Riyadh ')).toBe('riyadh')
  })

  it('matches a query anywhere and a prefix at the start', () => {
    expect(matchesQuery('جمعية الأمل النموذجية', 'الامل')).toBe(true)
    expect(matchesQuery('جمعية الأمل', '')).toBe(true)
    expect(matchesQuery('Makkah', 'riy')).toBe(false)
    expect(startsWithQuery('المنطقة الشرقية', 'المنطقه')).toBe(true)
    expect(startsWithQuery('المنطقة الشرقية', 'الشرقية')).toBe(false)
    expect(startsWithQuery('Riyadh', ' ')).toBe(false)
  })
})
