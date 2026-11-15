import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import Register from '@/views/auth/Register.vue'
import { termsService } from '@/services/termsService'
import { regionsService } from '@/services/regionsService'
import { authService } from '@/services/authService'
import OtpInput from '@/components/form/OtpInput.vue'
import { createTestGlobals } from '@/test-utils'
import {
  chooseNamedOption,
  findSelect,
  optionLabelsOf,
  optionValuesOf,
} from '@/test-utils/dropdown'

vi.mock('@/services/termsService', () => ({ termsService: { current: vi.fn() } }))
vi.mock('@/services/regionsService', () => ({ regionsService: { listRegions: vi.fn() } }))
vi.mock('@/services/authService', () => ({
  authService: {
    register: vi.fn(),
    requestRegistrationEmailCode: vi.fn(),
    resendRegistrationEmailCode: vi.fn(),
    verifyRegistrationEmailCode: vi.fn(),
  },
}))

const TERMS = {
  terms: { id: 3, kind: 'terms', version: '2026-10', body: { ar: 'نص الشروط', en: 'Terms text' } },
  privacy: {
    id: 4,
    kind: 'privacy',
    version: '2026-10',
    body: { ar: 'نص الخصوصية', en: 'Privacy text' },
  },
}
const REGIONS = [
  {
    id: 1,
    code: 'riyadh',
    name: { ar: 'منطقة الرياض', en: 'Riyadh Region' },
    cities: [{ id: 1, code: 'riyadh', name: { ar: 'الرياض', en: 'Riyadh' } }],
  },
  {
    id: 2,
    code: 'makkah',
    name: { ar: 'منطقة مكة المكرمة', en: 'Makkah Region' },
    cities: [{ id: 12, code: 'jeddah', name: { ar: 'جدة', en: 'Jeddah' } }],
  },
]

async function mountRegister() {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  const wrapper = mount(Register, { global: globals })
  await flushPromises()
  return { wrapper, globals }
}

async function settle() {
  for (let round = 0; round < 3; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

const ENTITY_VALUES = {
  unifiedNumber: '7000000901',
  'name.ar': 'جمعية الوفاء الخيرية',
  'name.en': 'Al-Wafa Charity',
  foundingYear: '2016',
}
const ACCOUNT_VALUES = {
  authorizedPersonName: 'سلمان بن فهد الحربي',
  officialEmail: 'info@alwafa-charity.example.sa',
  password: 'Wafa-Charity-Account-2026',
  passwordConfirmation: 'Wafa-Charity-Account-2026',
}

async function setValues(wrapper, values) {
  for (const [name, value] of Object.entries(values))
    await wrapper.find(`[name="${name}"]`).setValue(value)
}

function nextButton(wrapper) {
  return wrapper.find('.entity-register__actions button[type="submit"]')
}

async function goNext(wrapper) {
  await wrapper.find('form').trigger('submit')
  await settle()
}

async function fillEntityStep(wrapper, overrides = {}) {
  await chooseNamedOption(wrapper, 'entityType', 'waqf')
  await setValues(wrapper, { ...ENTITY_VALUES, ...overrides })
  await chooseNamedOption(wrapper, 'regionId', '2')
  await chooseNamedOption(wrapper, 'cityId', '12')
  await chooseNamedOption(wrapper, 'developmentSector', 'health')
  await chooseNamedOption(wrapper, 'orgSize', 'small')
}

async function fill(wrapper, overrides = {}) {
  await fillEntityStep(wrapper, overrides)
  await goNext(wrapper)
  await setValues(wrapper, ACCOUNT_VALUES)
}

async function proveEmail(wrapper) {
  await wrapper.find('[data-testid="email-check-send"]').trigger('click')
  await settle()
  wrapper.findComponent(OtpInput).vm.$emit('update:modelValue', '482913')
  await wrapper.find('[data-testid="email-check-verify"]').trigger('click')
  await settle()
}

function stepFieldNames(wrapper) {
  return [
    ...new Set(
      wrapper.findAll('.entity-register__step [name]').map((field) => field.attributes('name')),
    ),
  ]
}

beforeEach(() => {
  vi.clearAllMocks()
  termsService.current.mockResolvedValue({ data: TERMS })
  regionsService.listRegions.mockResolvedValue({ data: REGIONS })
  authService.register.mockResolvedValue({ data: { step: 'pending_review' } })
  authService.requestRegistrationEmailCode.mockResolvedValue({
    data: {
      challengeId: 'c-1',
      channel: 'email',
      length: 6,
      expiresAt: new Date(Date.now() + 600000).toISOString(),
      resendAvailableAt: new Date(Date.now() + 60000).toISOString(),
    },
  })
  authService.verifyRegistrationEmailCode.mockResolvedValue({
    data: { emailVerificationToken: 'proof-1' },
  })
})

describe('Register', () => {
  it('asks for the entity first, in the agreed order and without field hints', async () => {
    const { wrapper } = await mountRegister()
    expect(wrapper.findAll('.wizard__step')).toHaveLength(2)
    expect(stepFieldNames(wrapper)).toEqual([
      'entityType',
      'unifiedNumber',
      'name.ar',
      'name.en',
      'regionId',
      'cityId',
      'developmentSector',
      'orgSize',
      'foundingYear',
      'employeesCount',
      'governanceGrade',
      'annualBeneficiariesCount',
    ])
    expect(wrapper.findAll('.entity-register__step .field__hint')).toHaveLength(0)
    expect(nextButton(wrapper).text()).toBe('Next')
  })

  it('offers the three entity types, lists the cities of the chosen region', async () => {
    const { wrapper } = await mountRegister()
    expect(optionValuesOf(findSelect(wrapper, { name: 'entityType' }))).toEqual([
      'civil_association',
      'civil_foundation',
      'waqf',
    ])
    expect(wrapper.find('[name="cityId"]').element.disabled).toBe(true)
    await chooseNamedOption(wrapper, 'regionId', '1')
    expect(optionLabelsOf(findSelect(wrapper, { name: 'cityId' }))).toEqual(['Riyadh'])
  })

  it('stays on the entity step until it is valid and requires the English name', async () => {
    const { wrapper } = await mountRegister()
    await fillEntityStep(wrapper, { 'name.en': '' })
    await goNext(wrapper)
    expect(wrapper.find('[name="officialEmail"]').exists()).toBe(false)
    expect(wrapper.find('[name="name.en"]').attributes('aria-invalid')).toBe('true')
    expect(wrapper.find('.wizard__step.is-invalid').exists()).toBe(true)

    await setValues(wrapper, { 'name.en': 'Al-Wafa Charity' })
    await goNext(wrapper)
    expect(stepFieldNames(wrapper)).toEqual([
      'authorizedPersonName',
      'phone',
      'officialEmail',
      'password',
      'passwordConfirmation',
      'termsAccepted',
      'marketingConsent',
    ])
    expect(wrapper.findAll('.entity-register__step .field__hint')).toHaveLength(0)
    expect(wrapper.find('[name="marketingConsent"]').element.checked).toBe(false)
  })

  it('keeps the entity answers when going back a step', async () => {
    const { wrapper } = await mountRegister()
    await fill(wrapper)
    await wrapper.find('.entity-register__actions button[type="button"]').trigger('click')
    await settle()
    expect(wrapper.find('[name="name.en"]').element.value).toBe('Al-Wafa Charity')
    expect(wrapper.find('[name="unifiedNumber"]').element.value).toBe('7000000901')
  })

  it('needs the terms and the proven e-mail, then registers and says the entity is under review', async () => {
    const { wrapper } = await mountRegister()
    await fill(wrapper)
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(authService.register).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('You must accept the terms and the privacy policy to register')
    expect(wrapper.text()).toContain('Verify the official e-mail first.')

    await proveEmail(wrapper)
    expect(authService.requestRegistrationEmailCode).toHaveBeenCalledWith({
      officialEmail: ACCOUNT_VALUES.officialEmail,
    })
    expect(authService.verifyRegistrationEmailCode).toHaveBeenCalledWith({
      challengeId: 'c-1',
      code: '482913',
    })
    expect(wrapper.find('[data-testid="email-check-done"]').exists()).toBe(true)
    await wrapper.find('[name="termsAccepted"]').setValue(true)
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(authService.register).toHaveBeenCalledWith(
      expect.objectContaining({
        name: { ar: 'جمعية الوفاء الخيرية', en: 'Al-Wafa Charity' },
        entityType: 'waqf',
        unifiedNumber: '7000000901',
        regionId: 2,
        cityId: 12,
        governanceGrade: null,
        employeesCount: null,
        annualBeneficiariesCount: null,
        hasStrategicPlan: null,
        termsVersionId: 3,
        privacyVersionId: 4,
        termsAccepted: true,
        marketingConsent: false,
        passwordConfirmation: 'Wafa-Charity-Account-2026',
        emailVerificationToken: 'proof-1',
      }),
    )
    expect(wrapper.find('[data-testid="registration-under-review"]').text()).toContain(
      'reviewing it',
    )
  })

  it('drops the proof when the e-mail is edited after it was verified', async () => {
    const { wrapper } = await mountRegister()
    await fill(wrapper)
    await proveEmail(wrapper)
    await wrapper.find('[name="officialEmail"]').setValue('other@alwafa.example.sa')
    await settle()
    expect(wrapper.find('[data-testid="email-check-done"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="email-check-send"]').exists()).toBe(true)
  })

  it('keeps the unified number digits only and returns to the entity step on a duplicate', async () => {
    const DUPLICATE =
      'This unified national number is already registered for another entity on the platform.'
    authService.register.mockRejectedValue({
      response: { status: 422 },
      normalized: { message: 'Invalid', fieldErrors: { unifiedNumber: [DUPLICATE] } },
    })
    const { wrapper } = await mountRegister()
    const input = wrapper.find('[name="unifiedNumber"]')
    await input.setValue('٧٠٠-٠٠ab٠٠٩٠١9999')
    expect(input.element.value).toBe('7000000901')
    expect(input.attributes()).toMatchObject({ inputmode: 'numeric', dir: 'ltr', maxlength: '10' })
    await fill(wrapper)
    await proveEmail(wrapper)
    await wrapper.find('[name="termsAccepted"]').setValue(true)
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(authService.register).toHaveBeenCalledWith(
      expect.objectContaining({ unifiedNumber: '7000000901' }),
    )
    expect(wrapper.find('[name="unifiedNumber"]').exists()).toBe(true)
    expect(wrapper.text()).toContain(DUPLICATE)
  })

  it('requires the unified number before leaving the entity step', async () => {
    const { wrapper } = await mountRegister()
    await fillEntityStep(wrapper, { unifiedNumber: '' })
    await goNext(wrapper)
    expect(wrapper.find('[name="officialEmail"]').exists()).toBe(false)
    expect(wrapper.find('[name="unifiedNumber"]').attributes('aria-invalid')).toBe('true')
  })

  it('explains that registration is unavailable while no terms are published', async () => {
    termsService.current.mockResolvedValue({ data: { terms: null, privacy: null } })
    const { wrapper } = await mountRegister()
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.text()).toContain('Registration is not available yet')
  })
})
