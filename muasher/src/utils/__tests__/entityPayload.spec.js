import { describe, expect, it } from 'vitest'
import { limitDecimalInput } from '@/utils/numerals'
import { createI18n } from 'vue-i18n'
import en from '@/i18n/locales/en.json'
import ar from '@/i18n/locales/ar.json'
import {
  emptyProfileValues,
  profileFormValues,
  profilePayload,
  registrationPayload,
  unifiedNumberPayload,
} from '@/utils/entityPayload'
import {
  formatGovernanceGrade,
  governanceGradePayload,
  isValidGovernanceGrade,
} from '@/utils/governanceGrade'
import {
  permanentDeletionSchema,
  registrationSchema,
  unifiedNumberField,
} from '@/utils/entitySchemas'
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
    expect(profileFormValues(ENTITY)).toMatchObject({
      regionId: '1',
      cityId: '1',
      phone: '0500000000',
      hasStrategicPlan: 'yes',
    })
    expect(profilePayload(values({ name: { ar: ' جمعية البر بالرياض ', en: ' ' } }))).toEqual({
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

  it('sends the current terms and privacy versions at registration without a marketing choice', () => {
    const form = {
      ...emptyProfileValues(),
      ...values(),
      officialEmail: ' info@alwafa.example.sa ',
      password: 'p',
      passwordConfirmation: 'p',
      termsAccepted: true,
    }
    const payload = registrationPayload(
      { ...form, unifiedNumber: '7000000901', foundedOn: '2009-04-20', licenseNumber: ' ١٢٣٤ ' },
      { terms: { id: 3 }, privacy: { id: 4 } },
    )
    expect(payload).toMatchObject({
      officialEmail: 'info@alwafa.example.sa',
      unifiedNumber: '7000000901',
      termsVersionId: 3,
      privacyVersionId: 4,
      termsAccepted: true,
      foundedOn: '2009-04-20',
      foundingYear: 2009,
      licenseNumber: '1234',
    })
    expect(payload).not.toHaveProperty('marketingConsent')
    expect(
      registrationPayload(
        { ...form, foundedOn: '2009-04-20', licenseNumber: '' },
        { terms: { id: 3 }, privacy: null },
      ),
    ).not.toHaveProperty('privacyVersionId')
    expect(
      registrationPayload(
        { ...form, foundedOn: '2009-04-20', licenseNumber: '' },
        { terms: { id: 3 } },
      ).licenseNumber,
    ).toBeNull()
  })

  it('keeps an unanswered strategic plan as empty and sends it back as null (P-6)', () => {
    expect(profileFormValues({ ...ENTITY, hasStrategicPlan: null }).hasStrategicPlan).toBe('')
    expect(profilePayload(values({ hasStrategicPlan: '' })).hasStrategicPlan).toBeNull()
    expect(profilePayload(values({ hasStrategicPlan: 'no' })).hasStrategicPlan).toBe(false)
  })
})

describe('entity schemas', () => {
  it('requires the terms, a 12-character password and a city at registration', async () => {
    const schema = registrationSchema(t)
    const form = {
      ...emptyProfileValues(),
      officialEmail: 'info@alwafa.example.sa',
      password: 'short',
      passwordConfirmation: 'other',
      termsAccepted: false,
    }
    const errors = await schema
      .validate(form, { abortEarly: false })
      .catch((error) => error.inner.map((item) => item.path))
    expect(errors).toEqual(
      expect.arrayContaining([
        'password',
        'passwordConfirmation',
        'termsAccepted',
        'cityId',
        'regionId',
        'name.ar',
        'unifiedNumber',
        'foundedOn',
      ]),
    )
    expect(errors).not.toContain('licenseNumber')
    expect(errors).not.toContain('foundingYear')
    expect(errors).not.toContain('governanceGrade')
  })

  it('accepts the permanent deletion only with the disclaimer, the exact reference and a 20-character reason', async () => {
    const schema = permanentDeletionSchema(t, 'ENT-007003')
    const valid = {
      disclaimerAcknowledged: true,
      confirmation: 'ent-007003',
      reason: 'Approved erasure request of the entity',
    }
    await expect(schema.validate(valid)).resolves.toBeTruthy()
    await expect(schema.validate({ ...valid, confirmation: 'ENT-000001' })).rejects.toThrow(
      'Type ENT-007003 exactly',
    )
    await expect(schema.validate({ ...valid, disclaimerAcknowledged: false })).rejects.toThrow(
      'Acknowledge that the deletion cannot be undone',
    )
    await expect(schema.validate({ ...valid, reason: 'Too short reason' })).rejects.toThrow(
      'Must be at least 20 characters',
    )
  })
})

describe('unified national number', () => {
  it('requires exactly 10 Western digits with any first digit and accepts Arabic-Indic input', async () => {
    const field = unifiedNumberField(t)
    await expect(field.validate('7001234567')).resolves.toBe('7001234567')
    await expect(field.validate('٧٠٠١٢٣٤٥٦٧')).resolves.toBeTruthy()
    await expect(field.validate('')).rejects.toThrow('This field is required')
    await expect(field.validate('6001234567')).resolves.toBe('6001234567')
    await expect(field.validate('70012345678')).rejects.toThrow('exactly 10 digits')
    await expect(field.validate('700123456')).rejects.toThrow('exactly 10 digits')
    await expect(unifiedNumberField(t, { required: false }).validate('')).resolves.toBe('')
  })

  it('sends the number only when one is entered, in Western digits', () => {
    expect(unifiedNumberPayload('٧٠٠١٢٣٤٥٦٧')).toEqual({ unifiedNumber: '7001234567' })
    expect(unifiedNumberPayload('')).toEqual({})
  })
})

describe('limitDecimalInput', () => {
  it('keeps digits and one point, then trims the whole and the decimal parts', () => {
    const limits = { integerDigits: 3, decimals: 2 }
    expect(limitDecimalInput('21343.5678', limits)).toBe('213.56')
    expect(limitDecimalInput('9.1.2', limits)).toBe('9.12')
    expect(limitDecimalInput('ab12', limits)).toBe('12')
    expect(limitDecimalInput('', limits)).toBe('')
    expect(limitDecimalInput('85.', limits)).toBe('85.')
    expect(limitDecimalInput('85.5', { integerDigits: 3, decimals: 0 })).toBe('85')
  })
})
