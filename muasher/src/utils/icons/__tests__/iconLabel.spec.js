import { describe, expect, it } from 'vitest'
import ar from '@/i18n/locales/ar.json'
import en from '@/i18n/locales/en.json'
import { iconLabelKey } from '@/utils/icons/iconLabel'
import { PICKABLE_ICON_NAMES } from '@/utils/icons/iconRegistry'
import { KPI_ICON_OPTIONS } from '@/constants/kpis'

function messageAt(messages, key) {
  return key.split('.').reduce((node, part) => node?.[part], messages)
}

describe('icon labels', () => {
  it('builds a camel-case translation key from the icon name', () => {
    expect(iconLabelKey('chart-line')).toBe('icons.names.chartLine')
    expect(iconLabelKey('x')).toBe('icons.names.x')
  })

  it('translates every pickable icon in Arabic and English, never as the raw key', () => {
    const names = [...new Set([...PICKABLE_ICON_NAMES, ...KPI_ICON_OPTIONS])]
    const untranslated = names.filter((name) => {
      const arabic = messageAt(ar, iconLabelKey(name))
      const english = messageAt(en, iconLabelKey(name))
      return !/\p{Script=Arabic}/u.test(arabic ?? '') || !english || english === name
    })
    expect(untranslated).toEqual([])
    expect(names.length).toBeGreaterThan(KPI_ICON_OPTIONS.length)
  })
})
