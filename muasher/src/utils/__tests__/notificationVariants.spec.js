import { describe, expect, it } from 'vitest'
import { notificationVariantLabel } from '@/utils/notificationVariants'
import { createTestGlobals } from '@/test-utils'

describe('notificationVariantLabel', () => {
  const { t, te } = createTestGlobals({ locale: 'ar' }).i18n.global

  it('translates every segment of a variant and joins them', () => {
    expect(notificationVariantLabel('continued.entity', t, te)).toBe('عند المتابعة — إلى الجهة')
    expect(notificationVariantLabel('', t, te)).toBe('نص الإشعار')
    expect(notificationVariantLabel('unknown_segment', t, te)).toBe('unknown_segment')
  })
})

describe('notification variable and variant labels', () => {
  it('describe the same variables, variants and parts in Arabic and English', async () => {
    const ar = (await import('@/i18n/locales/ar.json')).default.settings.notifications.texts
    const en = (await import('@/i18n/locales/en.json')).default.settings.notifications.texts
    for (const group of ['variableNames', 'variants', 'parts']) {
      expect(Object.keys(ar[group]).sort()).toEqual(Object.keys(en[group]).sort())
    }
    expect(Object.keys(ar.variableNames).length).toBeGreaterThanOrEqual(56)
  })
})
