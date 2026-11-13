import { describe, expect, it } from 'vitest'
import {
  changedPathSettings,
  pathSettingsSchema,
  pathSettingsValues,
} from '@/utils/settings/pathSettingsForm'
import {
  certificateSettingsSchema,
  certificateSettingsValues,
  changedCertificateSettings,
  levelRangesOf,
} from '@/utils/settings/certificateSettingsForm'
import { toPathSettings } from '@/services/mappers/pathSettings'
import {
  authSettingsValues,
  changedAuthSettings,
  smsChannelLocked,
  withSmsChannel,
} from '@/utils/settings/authSettingsForm'

const t = (key) => key

const ACCREDITED = toPathSettings({
  path: 'accredited',
  reviewSlaDays: 14,
  reviewSlaLimits: { min: 1, max: 120 },
  uploadLimits: { maxFileMb: 20, allowedTypes: ['pdf', 'docx'], maxFilesPerSub: 10 },
  uploadLimitBounds: { maxFileMb: 100, maxFilesPerSub: 50 },
  evidenceTypeCatalog: ['pdf', 'docx', 'png'],
  isPaid: true,
  price: {
    priceBeforeVat: '1000.00',
    vatRate: '15.00',
    vatAmount: '150.00',
    total: '1150.00',
    currency: 'SAR',
  },
  entityTexts: { intro: { ar: 'تعريف', en: 'Intro' }, instructions: null, afterSubmission: null },
})

describe('per-path settings form', () => {
  it('starts from the server values and sends nothing when nothing changed', () => {
    const values = pathSettingsValues(ACCREDITED)
    expect(values).toMatchObject({ reviewSlaDays: '14' })
    expect(values).not.toHaveProperty('price')
    expect(changedPathSettings(ACCREDITED, values)).toEqual({})
  })

  it('sends the changed limits and texts only, never a price', () => {
    const values = pathSettingsValues(ACCREDITED)
    values.reviewSlaDays = '٢١'
    values.evidenceAllowedTypes = ['pdf']
    values.price = '1250.5'
    values.entityTexts.intro = { ar: '', en: '' }
    values.entityTexts.instructions = { ar: 'خطوة أولى', en: 'First step' }
    expect(changedPathSettings(ACCREDITED, values)).toEqual({
      reviewSlaDays: 21,
      evidenceAllowedTypes: ['pdf'],
      entityTexts: { intro: null, instructions: { ar: 'خطوة أولى', en: 'First step' } },
    })
  })

  it('lets a paid path without a price save its other settings', async () => {
    const unpriced = { ...ACCREDITED, price: null }
    const values = { ...pathSettingsValues(unpriced), reviewSlaDays: '20' }
    await expect(pathSettingsSchema(t, unpriced).validate(values)).resolves.toBeTruthy()
    expect(changedPathSettings(unpriced, values)).toEqual({ reviewSlaDays: 20 })
  })

  it('needs both languages of a text and limits within the bounds', async () => {
    const values = pathSettingsValues(ACCREDITED)
    values.entityTexts.afterSubmission = { ar: 'نص عربي', en: '' }
    values.uploadMaxFileMb = '101'
    const schema = pathSettingsSchema(t, ACCREDITED)
    const failure = await schema.validate(values, { abortEarly: false }).catch((error) => error)
    expect(failure.inner.map((issue) => issue.path).sort()).toEqual([
      'entityTexts.afterSubmission.en',
      'uploadMaxFileMb',
    ])
  })
})

describe('certificate settings form', () => {
  const settings = {
    accreditedIssuanceEnabled: true,
    levelThresholds: { bronze: '60.00', silver: '70.00', gold: '80.00', diamond: '90.00' },
  }

  it('sends the four thresholds together with two decimals when one changes', () => {
    const values = certificateSettingsValues(settings)
    expect(changedCertificateSettings(settings, values)).toEqual({})
    values.levelThresholds.silver = '72.5'
    values.accreditedIssuanceEnabled = false
    expect(changedCertificateSettings(settings, values)).toEqual({
      accreditedIssuanceEnabled: false,
      levelThresholds: { bronze: '60.00', silver: '72.50', gold: '80.00', diamond: '90.00' },
    })
  })

  it('refuses thresholds that are not ascending on the first broken level', async () => {
    const values = certificateSettingsValues(settings)
    values.levelThresholds.gold = '65'
    const failure = await certificateSettingsSchema(t)
      .validate(values, { abortEarly: false })
      .catch((error) => error)
    expect(failure.inner.map((issue) => issue.path)).toEqual(['levelThresholds.gold'])
    expect(failure.errors).toEqual(['settings.certificates.validation.ascending'])
  })

  it('derives the resulting ranges, or none while the values are invalid', () => {
    expect(levelRangesOf(settings.levelThresholds)).toEqual([
      { level: 'bronze', from: '60.00', to: '69.99' },
      { level: 'silver', from: '70.00', to: '79.99' },
      { level: 'gold', from: '80.00', to: '89.99' },
      { level: 'diamond', from: '90.00', to: null },
    ])
    expect(levelRangesOf({ ...settings.levelThresholds, gold: '65' })).toEqual([])
  })
})

describe('sign-in security form', () => {
  const EMAIL_ONLY = { entitySignInCodeChannels: ['email'], smsStatus: 'test' }

  it('sends the full channel list only when it changed', () => {
    const values = authSettingsValues(EMAIL_ONLY)
    expect(values).toEqual({ entitySignInCodeChannels: ['email'] })
    expect(changedAuthSettings(EMAIL_ONLY, values)).toEqual({})
    const withSms = {
      entitySignInCodeChannels: withSmsChannel(values.entitySignInCodeChannels, true),
    }
    expect(changedAuthSettings(EMAIL_ONLY, withSms)).toEqual({
      entitySignInCodeChannels: ['email', 'sms'],
    })
    expect(withSmsChannel(['email', 'sms'], false)).toEqual(['email'])
    expect(withSmsChannel([], true)).toEqual(['email', 'sms'])
  })

  it('locks the change only while SMS is unavailable and not saved', () => {
    expect(smsChannelLocked({ ...EMAIL_ONLY, smsStatus: 'unavailable' })).toBe(true)
    expect(
      smsChannelLocked({ entitySignInCodeChannels: ['email', 'sms'], smsStatus: 'unavailable' }),
    ).toBe(false)
    expect(smsChannelLocked(EMAIL_ONLY)).toBe(false)
  })
})
