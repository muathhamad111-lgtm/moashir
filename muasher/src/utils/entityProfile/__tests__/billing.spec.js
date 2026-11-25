import { describe, expect, it } from 'vitest'
import { createI18n } from 'vue-i18n'
import en from '@/i18n/locales/en.json'
import ar from '@/i18n/locales/ar.json'
import { sectionFormValues, sectionPayload } from '@/utils/entityProfile/formValues'
import { profileSectionSchema } from '@/utils/entityProfile/schema'
import { formAnswersOf, isFieldShown, shownFieldsOf } from '@/utils/entityProfile/sections'
import { entityDocumentIssue } from '@/utils/entityDocumentRules'
import { billingCompleteOf, toBilling } from '@/services/mappers/billing'

const { t } = createI18n({ legacy: false, locale: 'en', messages: { en, ar } }).global
const BILLING = [
  'addressBuildingNumber',
  'addressStreet',
  'addressDistrict',
  'addressCity',
  'addressPostalCode',
  'addressAdditionalNumber',
  'addressShortCode',
  'vatRegistered',
  'vatNumber',
  'vatCertificate',
]
const REQUIRED = BILLING.filter((name) => name !== 'addressShortCode')

const VALUES = {
  addressBuildingNumber: '2154',
  addressStreet: 'شارع الأمير سلطان',
  addressDistrict: 'حي الروضة',
  addressCity: 'جدة',
  addressPostalCode: '23435',
  addressAdditionalNumber: '8027',
  addressShortCode: '',
  vatRegistered: 'yes',
  vatNumber: '300060010000003',
}

function schema() {
  return profileSectionSchema(t, { fields: BILLING, required: REQUIRED, today: '2026-11-19' })
}

async function errorsOf(values) {
  try {
    await schema().validate(values, { abortEarly: false })
    return []
  } catch (error) {
    return error.inner.map((issue) => issue.path)
  }
}

describe('billing section form values', () => {
  it('reads the profile into form values, leaving the certificate out', () => {
    const values = sectionFormValues(
      { ...VALUES, vatRegistered: false, vatCertificate: { id: 'f-1' } },
      BILLING,
    )
    expect(values.vatRegistered).toBe('no')
    expect(values).not.toHaveProperty('vatCertificate')
    expect(sectionFormValues({}, BILLING).vatRegistered).toBe('')
  })

  it('normalises digits, upper-cases the short address and sends the yes answer', () => {
    const payload = sectionPayload(
      { ...VALUES, addressBuildingNumber: '٢١٥٤', addressShortCode: ' jrda2154 ' },
      BILLING,
      'rev-1',
    )
    expect(payload).toEqual({
      baseRevision: 'rev-1',
      addressBuildingNumber: '2154',
      addressStreet: 'شارع الأمير سلطان',
      addressDistrict: 'حي الروضة',
      addressCity: 'جدة',
      addressPostalCode: '23435',
      addressAdditionalNumber: '8027',
      addressShortCode: 'JRDA2154',
      vatRegistered: true,
      vatNumber: '300060010000003',
    })
  })

  it('clears the VAT number when the answer is no', () => {
    const payload = sectionPayload({ ...VALUES, vatRegistered: 'no' }, BILLING, 'rev-1')
    expect(payload.vatRegistered).toBe(false)
    expect(payload.vatNumber).toBeNull()
  })
})

describe('billing section schema', () => {
  it('accepts a complete address with a valid VAT number', async () => {
    expect(await errorsOf(VALUES)).toEqual([])
  })

  it('flags each malformed part', async () => {
    const errors = await errorsOf({
      ...VALUES,
      addressBuildingNumber: '215',
      addressPostalCode: '2343',
      addressAdditionalNumber: '80271',
      addressShortCode: '2154JRDA',
      vatNumber: '210123456700003',
    })
    expect(errors).toEqual(
      expect.arrayContaining([
        'addressBuildingNumber',
        'addressPostalCode',
        'addressAdditionalNumber',
        'addressShortCode',
        'vatNumber',
      ]),
    )
  })

  it('asks for the VAT number only after a yes', async () => {
    expect(await errorsOf({ ...VALUES, vatNumber: '' })).toEqual(['vatNumber'])
    expect(await errorsOf({ ...VALUES, vatRegistered: 'no', vatNumber: '' })).toEqual([])
  })

  it('requires the address parts and the VAT answer', async () => {
    const errors = await errorsOf({ ...VALUES, addressStreet: '', vatRegistered: '' })
    expect(errors).toEqual(expect.arrayContaining(['addressStreet', 'vatRegistered']))
  })
})

describe('billing helpers', () => {
  it('shows the VAT number and certificate only to a VAT-registered entity', () => {
    const fields = BILLING.map((name) => ({ name }))
    expect(isFieldShown('vatNumber', { vatRegistered: false })).toBe(false)
    expect(isFieldShown('addressCity', { vatRegistered: false })).toBe(true)
    expect(shownFieldsOf(fields, { vatRegistered: true })).toHaveLength(10)
    expect(
      shownFieldsOf(fields, { vatRegistered: false }).map((field) => field.name),
    ).not.toContain('vatCertificate')
  })

  it('shows the governance grade only after a «yes» to the governance question', () => {
    expect(isFieldShown('governanceGrade', { hasGovernanceGrade: true })).toBe(true)
    expect(isFieldShown('governanceGrade', { hasGovernanceGrade: false })).toBe(false)
    expect(isFieldShown('governanceGrade', { hasGovernanceGrade: null })).toBe(false)
    expect(formAnswersOf({ hasGovernanceGrade: 'yes', vatRegistered: 'no' })).toEqual({
      hasGovernanceGrade: true,
      vatRegistered: false,
    })
  })

  it('accepts only a PDF within 5 MB', () => {
    expect(entityDocumentIssue(null)).toBe('required')
    expect(entityDocumentIssue({ name: 'a.png', type: 'image/png', size: 10 })).toBe('type')
    expect(entityDocumentIssue({ name: 'a.pdf', type: 'application/pdf', size: 6e6 })).toBe('size')
    expect(entityDocumentIssue({ name: 'a.pdf', type: 'application/pdf', size: 1e5 })).toBeNull()
  })

  it('maps the Finance billing object and its flag', () => {
    expect(toBilling(null)).toBeNull()
    expect(
      toBilling({ vatRegistered: true, vatCertificate: { id: 'f', name: 'c.pdf' } }),
    ).toMatchObject({
      addressCity: null,
      vatRegistered: true,
      vatCertificate: { id: 'f', name: 'c.pdf', sizeBytes: 0, uploadedAt: null },
    })
    expect(billingCompleteOf({ billingComplete: false })).toBe(false)
    expect(billingCompleteOf({})).toBeNull()
  })
})
