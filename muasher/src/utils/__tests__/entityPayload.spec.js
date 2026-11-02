import { describe, expect, it } from 'vitest'
import { createI18n } from 'vue-i18n'
import en from '@/i18n/locales/en.json'
import ar from '@/i18n/locales/ar.json'
import { emptyProfileValues, profileChanges, profileFormValues, profilePayload, registrationPayload } from '@/utils/entityPayload'
import { formatGovernanceGrade, governanceGradePayload, isValidGovernanceGrade } from '@/utils/governanceGrade'
import { permanentDeletionSchema, registrationSchema } from '@/utils/entitySchemas'
import { toWesternDigits } from '@/utils/numerals'

const { t } = createI18n({ legacy: false, locale: 'en', messages: { en, ar } }).global

const ENTITY = {
  name: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity' },
  authorizedPersonName: 'أحمد بن سالم القحطاني',
  phone: '+966500000000',
  entityType: 'civil_association',
  developmentSector: 'social',
  orgSize: 'large',
  foundingYear: 2009,
  region: { id: 1 },
  city: { id: 1 },
  governanceGrade: '86.50',
  employeesCount: 180,
  annualBeneficiariesCount: null,
  hasStrategicPlan: true,
}

function values(overrides = {}) {
  return { ...profileFormValues(ENTITY), ...overrides }
}

describe('governance grade', () => {
  it('accepts 0 to 100 with at most two decimals and Eastern digits typed by the user', () => {
    expect(isValidGovernanceGrade('100')).toBe(true)
    expect(isValidGovernanceGrade('87.45')).toBe(true)
    expect(isValidGovernanceGrade('')).toBe(true)
    expect(isValidGovernanceGrade('87.456')).toBe(false)
    expect(isValidGovernanceGrade('100.01')).toBe(false)
    expect(isValidGovernanceGrade('-1')).toBe(false)
    const easternEightySeven = String.fromCharCode(0x668, 0x667)
    expect(toWesternDigits(easternEightySeven)).toBe('87')
    expect(governanceGradePayload('87.5')).toBe('87.50')
    expect(governanceGradePayload(' ')).toBeNull()
    expect(formatGovernanceGrade('86.5')).toBe('86.50%')
  })
})

describe('entity payload mapping', () => {
  it('maps the record to form values and back to the snake-ready payload', () => {
    expect(profileFormValues(ENTITY)).toMatchObject({ regionId: '1', cityId: '1', phone: '0500000000', hasStrategicPlan: 'yes' })
    expect(profilePayload(values({ 'name': { ar: ' جمعية البر بالرياض ', en: ' ' } }))).toEqual({
      name: { ar: 'جمعية البر بالرياض', en: null },
      authorizedPersonName: 'أحمد بن سالم القحطاني',
      phone: '0500000000',
      entityType: 'civil_association',
      developmentSector: 'social',
      orgSize: 'large',
      foundingYear: 2009,
      regionId: 1,
      cityId: 1,
      governanceGrade: '86.50',
      employeesCount: 180,
      annualBeneficiariesCount: null,
      hasStrategicPlan: true,
    })
  })

  it('sends the current terms and privacy versions and the explicit marketing choice at registration', () => {
    const form = { ...emptyProfileValues(), ...values(), officialEmail: ' info@alwafa.example.sa ', password: 'p', passwordConfirmation: 'p', termsAccepted: true, marketingConsent: false }
    const payload = registrationPayload(form, { terms: { id: 3 }, privacy: { id: 4 } })
    expect(payload).toMatchObject({ officialEmail: 'info@alwafa.example.sa', termsVersionId: 3, privacyVersionId: 4, termsAccepted: true, marketingConsent: false })
    expect(registrationPayload(form, { terms: { id: 3 }, privacy: null })).not.toHaveProperty('privacyVersionId')
  })

  it('lists only the changed fields for the change review', () => {
    const before = values()
    const after = values({ governanceGrade: '91.25', name: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity Riyadh' } })
    expect(profileChanges(before, after)).toEqual([
      { field: 'name.en', before: 'Al-Birr Charity', after: 'Al-Birr Charity Riyadh' },
      { field: 'governanceGrade', before: '86.50', after: '91.25' },
    ])
  })
})

describe('entity schemas', () => {
  it('requires the terms, a 12-character password and a city at registration', async () => {
    const schema = registrationSchema(t)
    const form = { ...emptyProfileValues(), officialEmail: 'info@alwafa.example.sa', password: 'short', passwordConfirmation: 'other', termsAccepted: false, marketingConsent: false }
    const errors = await schema.validate(form, { abortEarly: false }).catch((error) => error.inner.map((item) => item.path))
    expect(errors).toEqual(expect.arrayContaining(['password', 'passwordConfirmation', 'termsAccepted', 'cityId', 'regionId', 'name.ar']))
    expect(errors).not.toContain('marketingConsent')
    expect(errors).not.toContain('governanceGrade')
  })

  it('accepts the permanent deletion only with the disclaimer, the exact reference and a 20-character reason', async () => {
    const schema = permanentDeletionSchema(t, 'ENT-007003')
    const valid = { disclaimerAcknowledged: true, confirmation: 'ent-007003', reason: 'Approved erasure request of the entity' }
    await expect(schema.validate(valid)).resolves.toBeTruthy()
    await expect(schema.validate({ ...valid, confirmation: 'ENT-000001' })).rejects.toThrow('Type ENT-007003 exactly')
    await expect(schema.validate({ ...valid, disclaimerAcknowledged: false })).rejects.toThrow('Acknowledge that the deletion cannot be undone')
    await expect(schema.validate({ ...valid, reason: 'Too short reason' })).rejects.toThrow('Must be at least 20 characters')
  })
})
