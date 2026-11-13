import { describe, expect, it } from 'vitest'
import ar from '@/i18n/locales/ar.json'
import en from '@/i18n/locales/en.json'
import { AUTH_PANEL_VALUES } from '@/constants/authPanel'
import { hasIcon } from '@/utils/icons/iconRegistry'

function messageAt(messages, path) {
  return path.split('.').reduce((node, segment) => node?.[segment], messages)
}

describe('auth panel values', () => {
  it('lists the three core values in the approved order with stable keys', () => {
    expect(AUTH_PANEL_VALUES.map((value) => value.key)).toEqual([
      'standards',
      'review',
      'improvement',
    ])
    expect(Object.isFrozen(AUTH_PANEL_VALUES)).toBe(true)
    expect(AUTH_PANEL_VALUES.every((value) => Object.isFrozen(value))).toBe(true)
  })

  it('uses the approved icon names, each resolvable by the registry', () => {
    expect(AUTH_PANEL_VALUES.map((value) => value.icon)).toEqual([
      'list-check',
      'user-check',
      'chart-line',
    ])
    expect(AUTH_PANEL_VALUES.every((value) => hasIcon(value.icon))).toBe(true)
  })

  it.each([
    ['ar', ar],
    ['en', en],
  ])('has a title and a body for every value in %s', (_locale, messages) => {
    for (const value of AUTH_PANEL_VALUES) {
      expect(messageAt(messages, value.titleKey)).toEqual(expect.any(String))
      expect(messageAt(messages, value.bodyKey)).toEqual(expect.any(String))
    }
  })
})
