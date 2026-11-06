import { describe, expect, it } from 'vitest'
import { createI18n } from 'vue-i18n'
import { arabicPluralIndex, PLURAL_RULES } from '@/utils/arabicPlural'

const fallback = (choice, length) => Math.min(Math.abs(choice), length - 1)

describe('Arabic plural rule', () => {
  it.each([
    [0, 0],
    [1, 1],
    [2, 2],
    [3, 3],
    [10, 3],
    [11, 4],
    [99, 4],
    [100, 5],
    [102, 5],
  ])('maps %i to form %i of six', (count, form) => {
    expect(arabicPluralIndex(count, 6, fallback)).toBe(form)
  })

  it('keeps the default rule for the three-form messages', () => {
    expect(arabicPluralIndex(7, 3, fallback)).toBe(2)
  })

  it('selects the Arabic forms through vue-i18n', () => {
    const i18n = createI18n({
      legacy: false,
      locale: 'ar',
      pluralRules: PLURAL_RULES,
      messages: { ar: { item: 'صفر | واحد | اثنان | {count} قليل | {count} كثير | {count} أخرى' } },
    })
    const { t } = i18n.global
    expect([0, 1, 2, 3, 11, 100].map((count) => t('item', count))).toEqual([
      'صفر',
      'واحد',
      'اثنان',
      '3 قليل',
      '11 كثير',
      '100 أخرى',
    ])
  })
})
