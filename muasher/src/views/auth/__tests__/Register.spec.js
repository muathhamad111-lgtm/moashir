import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import Register from '@/views/auth/Register.vue'
import { termsService } from '@/services/termsService'
import { regionsService } from '@/services/regionsService'
import { authService } from '@/services/authService'
import { useAuthFlowStore } from '@/stores/authFlow'
import { createTestGlobals } from '@/test-utils'
import {
  chooseNamedOption,
  findSelect,
  optionLabelsOf,
  optionValuesOf,
} from '@/test-utils/dropdown'

vi.mock('@/services/termsService', () => ({ termsService: { current: vi.fn() } }))
vi.mock('@/services/regionsService', () => ({ regionsService: { listRegions: vi.fn() } }))
vi.mock('@/services/authService', () => ({ authService: { register: vi.fn() } }))

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

async function fill(wrapper) {
  const values = {
    officialEmail: 'info@alwafa-charity.example.sa',
    password: 'Wafa-Charity-Account-2026',
    passwordConfirmation: 'Wafa-Charity-Account-2026',
    'name.ar': 'جمعية الوفاء الخيرية',
    unifiedNumber: '7000000901',
    authorizedPersonName: 'سلمان بن فهد الحربي',
    foundingYear: '2016',
  }
  for (const [name, value] of Object.entries(values))
    await wrapper.find(`[name="${name}"]`).setValue(value)
  await chooseNamedOption(wrapper, 'entityType', 'waqf')
  await chooseNamedOption(wrapper, 'developmentSector', 'health')
  await chooseNamedOption(wrapper, 'orgSize', 'small')
  await chooseNamedOption(wrapper, 'regionId', '2')
  await chooseNamedOption(wrapper, 'cityId', '12')
}

beforeEach(() => {
  vi.clearAllMocks()
  termsService.current.mockResolvedValue({ data: TERMS })
  regionsService.listRegions.mockResolvedValue({ data: REGIONS })
  authService.register.mockResolvedValue({
    data: { step: 'otp_required', otp: { challengeId: 'c-1', length: 6 }, message: 'Code sent' },
  })
})

describe('Register', () => {
  it('offers the three entity types, lists the cities of the chosen region and leaves marketing unticked', async () => {
    const { wrapper } = await mountRegister()
    expect(optionValuesOf(findSelect(wrapper, { name: 'entityType' }))).toEqual([
      'civil_association',
      'civil_foundation',
      'waqf',
    ])
    expect(wrapper.find('[name="cityId"]').element.disabled).toBe(true)
    await chooseNamedOption(wrapper, 'regionId', '1')
    expect(optionLabelsOf(findSelect(wrapper, { name: 'cityId' }))).toEqual(['Riyadh'])
    expect(wrapper.find('[name="marketingConsent"]').element.checked).toBe(false)
  })

  it('blocks the submission until the terms are accepted, then registers and opens the OTP step', async () => {
    const { wrapper, globals } = await mountRegister()
    const push = vi.spyOn(globals.router, 'push').mockResolvedValue(undefined)
    await fill(wrapper)
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(authService.register).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('You must accept the terms and the privacy policy to register')

    await wrapper.find('[name="termsAccepted"]').setValue(true)
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(authService.register).toHaveBeenCalledWith(
      expect.objectContaining({
        entityType: 'waqf',
        unifiedNumber: '7000000901',
        regionId: 2,
        cityId: 12,
        governanceGrade: null,
        termsVersionId: 3,
        privacyVersionId: 4,
        termsAccepted: true,
        marketingConsent: false,
        passwordConfirmation: 'Wafa-Charity-Account-2026',
      }),
    )
    expect(useAuthFlowStore().intent).toBe('register')
    expect(push).toHaveBeenCalledWith({ name: 'auth.otp' })
  })

  it('keeps the unified number digits only, explains it and shows the duplicate answer on the field', async () => {
    const DUPLICATE =
      'This unified national number is already registered for another entity on the platform.'
    authService.register.mockRejectedValue({
      response: { status: 422 },
      normalized: { message: 'Invalid', fieldErrors: { unifiedNumber: [DUPLICATE] } },
    })
    const { wrapper } = await mountRegister()
    await fill(wrapper)
    const input = wrapper.find('[name="unifiedNumber"]')
    await input.setValue('٧٠٠-٠٠ab٠٠٩٠١9999')
    expect(input.element.value).toBe('7000000901')
    expect(input.attributes()).toMatchObject({ inputmode: 'numeric', dir: 'ltr', maxlength: '10' })
    expect(wrapper.text()).toContain('10 digits starting with 7.')
    await wrapper.find('[name="termsAccepted"]').setValue(true)
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(authService.register).toHaveBeenCalledWith(
      expect.objectContaining({ unifiedNumber: '7000000901' }),
    )
    expect(wrapper.text()).toContain(DUPLICATE)
  })

  it('requires the unified number before registering', async () => {
    const { wrapper } = await mountRegister()
    await fill(wrapper)
    await wrapper.find('[name="unifiedNumber"]').setValue('')
    await wrapper.find('[name="termsAccepted"]').setValue(true)
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(authService.register).not.toHaveBeenCalled()
    expect(wrapper.find('[name="unifiedNumber"]').attributes('aria-invalid')).toBe('true')
  })

  it('explains that registration is unavailable while no terms are published', async () => {
    termsService.current.mockResolvedValue({ data: { terms: null, privacy: null } })
    const { wrapper } = await mountRegister()
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.text()).toContain('Registration is not available yet')
  })
})
