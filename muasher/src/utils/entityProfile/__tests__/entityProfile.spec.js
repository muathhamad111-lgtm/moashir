import { describe, expect, it } from 'vitest'
import { createI18n } from 'vue-i18n'
import en from '@/i18n/locales/en.json'
import ar from '@/i18n/locales/ar.json'
import { sectionFormValues, sectionPayload } from '@/utils/entityProfile/formValues'
import { profileSectionSchema } from '@/utils/entityProfile/schema'
import {
  fieldLabelKey,
  firstMissingOf,
  nextMissingAfter,
  sectionViewsOf,
} from '@/utils/entityProfile/sections'
import { toOwnEntity } from '@/services/mappers/ownEntity'

const { t } = createI18n({ legacy: false, locale: 'en', messages: { en, ar } }).global
const TODAY = '2026-11-11'
const IDENTITY = ['name.ar', 'name.en', 'unifiedNumber', 'licenseNumber', 'foundedOn']
const GOVERNANCE = [
  'governanceGrade',
  'employeesCount',
  'annualBeneficiariesCount',
  'hasStrategicPlan',
]
const CONTACT = ['officialEmail', 'authorizedPersonName', 'phone', 'websiteUrl', 'marketingConsent']

const RAW = {
  name: { ar: 'مؤسسة نماء للتنمية', en: null },
  unifiedNumber: null,
  licenseNumber: null,
  foundedOn: null,
  governanceGrade: null,
  employeesCount: null,
  annualBeneficiariesCount: null,
  hasStrategicPlan: null,
  officialEmail: 'info@namaa.example.sa',
  authorizedPersonName: 'نورة بنت فهد العتيبي',
  phone: '+966501234567',
  websiteUrl: null,
  marketingConsent: false,
  profileSections: [
    {
      key: 'identity',
      revision: 'r-identity',
      fields: [
        { key: 'name.ar', requirement: 'required' },
        { key: 'name.en', requirement: 'optional' },
        { key: 'unified_number', requirement: 'required' },
        { key: 'license_number', requirement: 'completion' },
        { key: 'founded_on', requirement: 'completion' },
      ],
    },
    {
      key: 'governance',
      revision: 'r-governance',
      fields: [
        { key: 'governance_grade', requirement: 'completion' },
        { key: 'has_strategic_plan', requirement: 'completion' },
      ],
    },
  ],
  profileCompletion: {
    percent: 60,
    counted: 15,
    completedCount: 9,
    sections: [
      {
        key: 'identity',
        state: 'incomplete',
        missingCount: 2,
        missingFields: ['unified_number', 'founded_on'],
      },
      { key: 'governance', state: 'complete', missingCount: 0, missingFields: [] },
    ],
  },
}

async function errorsOf(fields, required, values) {
  return profileSectionSchema(t, { fields, required, today: TODAY })
    .validate(values, { abortEarly: false })
    .then(() => [])
    .catch((error) => error.inner.map((issue) => issue.path))
}

describe('own entity mapping', () => {
  it('maps the API field keys to form names and keeps completed_count', () => {
    const profile = toOwnEntity(RAW)
    expect(profile.profileSections[0].fields.map((field) => field.name)).toEqual(IDENTITY)
    expect(profile.profileCompletion.completedCount).toBe(9)
    expect(profile.profileCompletion.sections[0].missingFields).toEqual([
      'unifiedNumber',
      'foundedOn',
    ])
    expect(profile.canEdit).toBe(false)
  })
})

describe('section views', () => {
  it('marks counted, required and missing fields from the API only', () => {
    const [identity] = sectionViewsOf(toOwnEntity(RAW))
    expect(identity.fields.find((field) => field.name === 'name.en')).toMatchObject({
      counted: false,
      required: false,
      missing: false,
    })
    expect(identity.fields.find((field) => field.name === 'unifiedNumber')).toMatchObject({
      counted: true,
      required: true,
      missing: true,
    })
    expect(firstMissingOf(sectionViewsOf(toOwnEntity(RAW)))).toEqual({
      section: 'identity',
      field: 'unifiedNumber',
    })
  })

  it('walks the missing fields in order and wraps around', () => {
    expect(nextMissingAfter(['a', 'b', 'c'], 'b')).toBe('c')
    expect(nextMissingAfter(['a', 'b', 'c'], 'c')).toBe('a')
    expect(nextMissingAfter(['a', 'b'], 'x')).toBe('a')
    expect(nextMissingAfter([], 'a')).toBeNull()
    expect(fieldLabelKey('name.ar')).toBe('portal.profile.fields.nameAr')
  })
})

describe('section payloads', () => {
  it('sends the base revision and every editable field, with empty values as null', () => {
    const values = sectionFormValues(RAW, IDENTITY)
    expect(values).toEqual({
      name: { ar: 'مؤسسة نماء للتنمية', en: '' },
      unifiedNumber: '',
      licenseNumber: '',
      foundedOn: '',
    })
    expect(
      sectionPayload(
        { ...values, unifiedNumber: '٧٠٠٠٠٠٠٧٧٧', licenseNumber: '٠٠٠١٢٣' },
        IDENTITY,
        'r-identity',
      ),
    ).toEqual({
      baseRevision: 'r-identity',
      name: { ar: 'مؤسسة نماء للتنمية', en: null },
      unifiedNumber: '7000000777',
      licenseNumber: '000123',
      foundedOn: null,
    })
  })

  it('never sends the official e-mail and keeps an unanswered plan as null', () => {
    const contact = sectionPayload(sectionFormValues(RAW, CONTACT), CONTACT, 'r-contact')
    expect(contact).not.toHaveProperty('officialEmail')
    expect(contact).toMatchObject({
      phone: '0501234567',
      websiteUrl: null,
      marketingConsent: false,
    })
    const governance = sectionPayload(
      sectionFormValues(RAW, GOVERNANCE),
      GOVERNANCE,
      'r-governance',
    )
    expect(governance).toEqual({
      baseRevision: 'r-governance',
      governanceGrade: null,
      employeesCount: null,
      annualBeneficiariesCount: null,
      hasStrategicPlan: null,
    })
    expect(sectionPayload({ hasStrategicPlan: 'no' }, ['hasStrategicPlan'], 'r')).toMatchObject({
      hasStrategicPlan: false,
    })
  })
})

describe('section schema', () => {
  it('requires the fields the API marks required and nothing else', async () => {
    const values = sectionFormValues(RAW, IDENTITY)
    expect(await errorsOf(IDENTITY, ['name.ar', 'unifiedNumber'], values)).toEqual([
      'unifiedNumber',
    ])
    expect(await errorsOf(IDENTITY, ['name.ar'], values)).toEqual([])
  })

  it('refuses a short licence, a future date, a grade above 100 and plain http', async () => {
    const identity = {
      name: { ar: 'مؤسسة نماء للتنمية', en: '' },
      unifiedNumber: '7000000777',
      licenseNumber: '12',
      foundedOn: '2026-12-01',
    }
    expect(await errorsOf(IDENTITY, [], identity)).toEqual(['licenseNumber', 'foundedOn'])
    expect(
      await errorsOf(['governanceGrade', 'employeesCount'], [], {
        governanceGrade: '120',
        employeesCount: '-3',
      }),
    ).toEqual(['governanceGrade', 'employeesCount'])
    expect(await errorsOf(['websiteUrl'], [], { websiteUrl: 'http://namaa.example.sa' })).toEqual([
      'websiteUrl',
    ])
  })
})
