import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import Register from '@/views/auth/Register.vue'
import { termsService } from '@/services/termsService'
import { regionsService } from '@/services/regionsService'
import { authService } from '@/services/authService'
import { useAuthFlowStore } from '@/stores/authFlow'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/termsService', () => ({ termsService: { current: vi.fn() } }))
vi.mock('@/services/regionsService', () => ({ regionsService: { listRegions: vi.fn() } }))
vi.mock('@/services/authService', () => ({ authService: { register: vi.fn() } }))

const TERMS = {
  terms: { id: 3, kind: 'terms', version: '2026-10', body: { ar: 'نص الشروط', en: 'Terms text' } },
  privacy: { id: 4, kind: 'privacy', version: '2026-10', body: { ar: 'نص الخصوصية', en: 'Privacy text' } },
}
const REGIONS = [
  { id: 1, code: 'riyadh', name: { ar: 'منطقة الرياض', en: 'Riyadh Region' }, cities: [{ id: 1, code: 'riyadh', name: { ar: 'الرياض', en: 'Riyadh' } }] },
  { id: 2, code: 'makkah', name: { ar: 'منطقة مكة المكرمة', en: 'Makkah Region' }, cities: [{ id: 12, code: 'jeddah', name: { ar: 'جدة', en: 'Jeddah' } }] },
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
    authorizedPersonName: 'سلمان بن فهد الحربي',
    foundingYear: '2016',
  }
  for (const [name, value] of Object.entries(values)) await wrapper.find(`[name="${name}"]`).setValue(value)
  await wrapper.find('[name="entityType"]').setValue('waqf')
  await wrapper.find('[name="developmentSector"]').setValue('health')
  await wrapper.find('[name="orgSize"]').setValue('small')
  await wrapper.find('[name="regionId"]').setValue('2')
  await flushPromises()
  await wrapper.find('[name="cityId"]').setValue('12')
}

beforeEach(() => {
  vi.clearAllMocks()
  termsService.current.mockResolvedValue({ data: TERMS })
  regionsService.listRegions.mockResolvedValue({ data: REGIONS })
  authService.register.mockResolvedValue({ data: { step: 'otp_required', otp: { challengeId: 'c-1', length: 6 }, message: 'Code sent' } })
})

describe('Register', () => {
  it('offers the three entity types, lists the cities of the chosen region and leaves marketing unticked', async () => {
    const { wrapper } = await mountRegister()
    expect(wrapper.findAll('[name="entityType"] option[value]:not([value=""])').map((option) => option.element.value)).toEqual([
      'civil_association',
      'civil_foundation',
      'waqf',
    ])
    expect(wrapper.find('[name="cityId"]').element.disabled).toBe(true)
    await wrapper.find('[name="regionId"]').setValue('1')
    await flushPromises()
    expect(wrapper.findAll('[name="cityId"] option[value]:not([value=""])').map((option) => option.text())).toEqual(['Riyadh'])
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

  it('explains that registration is unavailable while no terms are published', async () => {
    termsService.current.mockResolvedValue({ data: { terms: null, privacy: null } })
    const { wrapper } = await mountRegister()
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.text()).toContain('Registration is not available yet')
  })
})
