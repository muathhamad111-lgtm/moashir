import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import General from '@/views/settings/General.vue'
import { authSettingsService } from '@/services/authSettingsService'
import { financeSettingsService } from '@/services/financeSettingsService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/authSettingsService', () => ({
  authSettingsService: { get: vi.fn(), update: vi.fn() },
}))
vi.mock('@/services/financeSettingsService', () => ({
  financeSettingsService: { get: vi.fn(), update: vi.fn() },
}))

const SETTINGS = {
  entitySignInCodeChannels: ['email'],
  generalEmailNotifications: true,
  entityRegistrationReview: true,
  twoFactorEnabled: true,
  twoFactorRoles: ['system_admin', 'assessor'],
  smsStatus: 'test',
}

const FINANCE = {
  vatRate: '15.00',
  vatRegistrationNumber: null,
  orderValidityMinutes: 30,
  orderValidityLimits: { min: 5, max: 60 },
  editableFields: ['vat_rate', 'order_validity_minutes', 'vat_registration_number'],
}

let wrapper

async function mountScreen({ superAdmin = false, locale = 'en', settings = {}, permissions } = {}) {
  authSettingsService.get.mockResolvedValue({ data: { ...SETTINGS, ...settings } })
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  const user = sessionUserFor(['system_admin'], { id: 1, isSuperAdmin: superAdmin })
  useAuthStore(globals.pinia).setUser(permissions ? { ...user, permissions } : user)
  wrapper = mount(General, { global: globals, attachTo: document.body })
  await flushPromises()
  return wrapper
}

function box(name) {
  return wrapper.find(`input[name="${name}"]`)
}

function verification() {
  return wrapper.find('[data-testid="verification-settings"]')
}

async function settle() {
  for (let round = 0; round < 3; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

async function saveVerification() {
  await verification().trigger('submit')
  await settle()
}

beforeEach(() => {
  authSettingsService.update.mockImplementation((payload) =>
    Promise.resolve({ data: { ...SETTINGS, ...payload } }),
  )
  financeSettingsService.get.mockResolvedValue({ data: FINANCE })
  financeSettingsService.update.mockImplementation((payload) =>
    Promise.resolve({ data: { ...FINANCE, ...payload } }),
  )
})

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('General settings', () => {
  it('shows the verification mechanism, two-factor and tax, with short texts and no test notice', async () => {
    await mountScreen({ locale: 'ar' })
    expect(wrapper.find('h1').text()).toBe('عام')
    expect(wrapper.findAll('.settings-form__legend').map((legend) => legend.text())).toEqual([
      'آلية التحقق',
      'تسجيل الجهات الجديدة',
      'التحقق الثنائي',
      'رقم الهوية على شواهد التقييم',
      'الإدارة المالية',
    ])
    expect(box('codeChannelEmail').element.checked).toBe(true)
    expect(wrapper.find('.sign-in-channels__warning').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('الاختبار')
    expect(wrapper.text()).not.toContain('دون تحقق ثنائي')
    expect(
      wrapper
        .findAll('[data-testid="tax-settings"] input')
        .map((input) => input.attributes('name')),
    ).toEqual(['vatRegistrationNumber', 'vatRate', 'orderValidityMinutes'])
  })

  it('turns the e-mail of general notifications off and sends only that change', async () => {
    await mountScreen()
    await box('codeChannelEmail').setValue(false)
    await saveVerification()
    expect(authSettingsService.update).toHaveBeenCalledWith({ generalEmailNotifications: false })
  })

  it('switches the staff review of new entities off and sends only that change', async () => {
    await mountScreen()
    const toggle = wrapper.find('input[name="entityRegistrationReview"]')
    expect(toggle.element.checked).toBe(true)
    expect(wrapper.text()).toContain('Staff review before activation')
    await toggle.setValue(false)
    await saveVerification()
    expect(authSettingsService.update).toHaveBeenCalledWith({ entityRegistrationReview: false })
  })

  it('disables SMS until the gateway is enabled, while the rest can still be saved', async () => {
    await mountScreen({ settings: { smsStatus: 'unavailable' } })
    expect(box('codeChannelSms').attributes('disabled')).toBeDefined()
    expect(wrapper.find(`#${box('codeChannelSms').attributes('aria-describedby')}`).text()).toBe(
      'Requires the SMS gateway to be enabled first.',
    )
    expect(wrapper.find('.sign-in-channels__setup').attributes('href')).toBe('/admin/settings/sms')
    await box('twoFactorRoles-entity').setValue(true)
    await saveVerification()
    expect(authSettingsService.update).toHaveBeenCalledWith({
      twoFactorRoles: ['system_admin', 'assessor', 'entity'],
    })
  })

  it('saves the SMS channel once the gateway is available', async () => {
    await mountScreen({ settings: { smsStatus: 'live' } })
    await box('codeChannelSms').setValue(true)
    await saveVerification()
    expect(authSettingsService.update).toHaveBeenCalledWith({
      entitySignInCodeChannels: ['email', 'sms'],
    })
  })

  it('hides the role list while two-factor is off and sends the switch alone', async () => {
    await mountScreen()
    expect(wrapper.find('input[name="twoFactorRoles-system_admin"]').element.checked).toBe(true)
    await wrapper.find('input[name="twoFactorEnabled"]').setValue(false)
    expect(wrapper.find('.checkbox-group').isVisible()).toBe(false)
    await saveVerification()
    expect(authSettingsService.update).toHaveBeenCalledWith({ twoFactorEnabled: false })
  })

  it('asks the Super Admin for a reason and sends it with the change', async () => {
    await mountScreen({ superAdmin: true })
    await box('codeChannelEmail').setValue(false)
    await saveVerification()
    expect(authSettingsService.update).not.toHaveBeenCalled()
    const textarea = document.body.querySelector('textarea[name="reason"]')
    textarea.value = 'The owner approved quieter e-mail in writing.'
    textarea.dispatchEvent(new Event('input'))
    await flushPromises()
    const submit = [...document.body.querySelectorAll('.modal button')].find(
      (button) => button.textContent.trim() === 'Save',
    )
    submit.click()
    await settle()
    expect(authSettingsService.update).toHaveBeenCalledWith({
      generalEmailNotifications: false,
      reason: 'The owner approved quieter e-mail in writing.',
    })
  })

  it('saves the tax fields from their own card', async () => {
    await mountScreen()
    await wrapper.find('input[name="vatRegistrationNumber"]').setValue('300000000000003')
    await wrapper.find('[data-testid="tax-settings"]').trigger('submit')
    await settle()
    expect(financeSettingsService.update).toHaveBeenCalledWith(
      expect.objectContaining({ vatRegistrationNumber: '300000000000003' }),
    )
  })

  it('shows only the tax card to a payment settings manager', async () => {
    await mountScreen({ permissions: ['settings.payment.manage'] })
    expect(verification().exists()).toBe(false)
    expect(authSettingsService.get).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="tax-settings"]').exists()).toBe(true)
  })
})
