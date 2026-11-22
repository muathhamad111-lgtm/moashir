import { describe, expect, it } from 'vitest'
import ar from '@/i18n/locales/ar.json'
import en from '@/i18n/locales/en.json'
import { NOTIFICATION_MODULES } from '@/constants/notificationCatalogue'
import { NOTIFICATION_TYPE_CATALOG } from '@/mocks/fixtures/notificationTypes'

// Regression (2026-11-22): these types showed «إشعار بلا اسم معرَّف» in the settings because they had no name.
const ONCE_UNNAMED =
  'REG-04 REG-11 REG-12 REG-13 SEC-07 SEC-08 STD-01 STD-02 STD-03 STD-04 STD-05 STD-06 STD-07 SELF-01 SELF-02 SELF-04 ACC-02 ACC-04 ACC-06 ACC-13 PER-01 PER-02 PER-03 PER-04 PER-05 ADV-03 ADV-06 ADV-10 ADV-11 ADV-14 ADV-20 ADV-21 ADV-22 ADV-26 ADV-30 CYC-01 CYC-02 EVD-01 EVD-02 INT-02 INT-05 INT-06 AI-04 PAY-01 PAY-07 PAY-11 PAY-12 FIN-01 FIN-03 CMS-01 CMS-02 CMS-03 CMS-04 CMS-05'.split(
    ' ',
  )

describe('notification type names', () => {
  it.each(['ar', 'en'])('names every notification type in %s', (locale) => {
    const names = (locale === 'ar' ? ar : en).notifications.typeNames
    const codes = [...ONCE_UNNAMED, ...NOTIFICATION_TYPE_CATALOG.map((type) => type.code)]
    expect(codes.filter((code) => !names[code]?.trim())).toEqual([])
  })

  it.each(['ar', 'en'])('labels every module group in %s', (locale) => {
    const modules = (locale === 'ar' ? ar : en).notifications.modules
    expect(NOTIFICATION_MODULES.filter((module) => !modules[module]?.trim())).toEqual([])
  })
})
