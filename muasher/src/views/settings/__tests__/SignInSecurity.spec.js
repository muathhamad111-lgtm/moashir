import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import SignInSecurity from '@/views/settings/SignInSecurity.vue'
import { authSettingsService } from '@/services/authSettingsService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/authSettingsService', () => ({
  authSettingsService: { get: vi.fn(), update: vi.fn() },
}))

const SETTINGS = {
  entitySignInCodeChannels: ['email'],
  smsStatus: 'test',
  staffTotpRequired: true,
}

let wrapper

async function mountScreen({ superAdmin = false, locale = 'en', settings = {} } = {}) {
  authSettingsService.get.mockResolvedValue({ data: { ...SETTINGS, ...settings } })
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(
    sessionUserFor(superAdmin ? [] : ['system_admin'], { id: 1, isSuperAdmin: superAdmin }),
  )
  wrapper = mount(SignInSecurity, { global: globals, attachTo: document.body })
  await flushPromises()
  return wrapper
}

function emailBox() {
  return wrapper.find('input[name="codeChannelEmail"]')
}

function smsBox() {
  return wrapper.find('input[name="codeChannelSms"]')
}

function saveButton() {
  return wrapper.find('.settings-section__footer button[type="submit"]')
}

async function settle() {
  for (let round = 0; round < 3; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

async function save() {
  await wrapper.find('form').trigger('submit')
  await settle()
}

beforeEach(() => {
  authSettingsService.update.mockImplementation((payload) =>
    Promise.resolve({ data: { ...SETTINGS, ...payload } }),
  )
})

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('sign-in security: entity code channels', () => {
  it('states the entity sign-in, keeps the e-mail on and lists what always applies', async () => {
    await mountScreen({ locale: 'ar' })
    expect(authSettingsService.get).toHaveBeenCalled()
    expect(wrapper.find('.sign-in-security__statement').text()).toBe(
      'حسابات الجهات تدخل بكلمة المرور أو برمز تحقق لمرة واحدة، دون تحقق ثنائي.',
    )
    expect(emailBox().element.checked).toBe(true)
    expect(emailBox().attributes('disabled')).toBeDefined()
    const emailHelp = wrapper.find(`#${emailBox().attributes('aria-describedby')}`)
    expect(emailHelp.text()).toBe('مسموح دائمًا، ولا يمكن إيقافه.')
    expect(wrapper.findAll('.sign-in-security__fact').map((fact) => fact.text())).toEqual([
      'يبقى تطبيق المصادقة إلزاميًا لحسابات الموظفين ومدير النظام الرئيسي.',
      'يُسجَّل كل تغيير في سجل التدقيق، ويُبلَّغ به مديرو النظام.',
    ])
    expect(wrapper.find('.toggle-switch').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('قرار العميل')
  })

  it('locks the SMS box and the save button while the SMS gateway is not configured', async () => {
    await mountScreen({ settings: { smsStatus: 'unavailable' } })
    expect(wrapper.find('.sign-in-channels').attributes('data-sms-status')).toBe('unavailable')
    expect(smsBox().element.checked).toBe(false)
    expect(smsBox().attributes('disabled')).toBeDefined()
    expect(wrapper.find(`#${smsBox().attributes('aria-describedby')}`).text()).toBe(
      'The SMS gateway is not configured.',
    )
    expect(wrapper.find('.sign-in-channels__setup').attributes('href')).toBe('/admin/settings/sms')
    expect(wrapper.find('.sign-in-channels__warning').exists()).toBe(false)
    expect(saveButton().attributes('disabled')).toBeDefined()
  })

  it('lets the administrator remove a saved SMS channel after the gateway went away', async () => {
    await mountScreen({
      settings: { smsStatus: 'unavailable', entitySignInCodeChannels: ['email', 'sms'] },
    })
    expect(smsBox().element.checked).toBe(true)
    expect(smsBox().attributes('disabled')).toBeUndefined()
    expect(saveButton().attributes('disabled')).toBeUndefined()
    await smsBox().setValue(false)
    await save()
    expect(authSettingsService.update).toHaveBeenCalledWith({ entitySignInCodeChannels: ['email'] })
  })

  it('warns about the test mode and saves the SMS channel', async () => {
    await mountScreen()
    expect(wrapper.find('.sign-in-channels__warning').text()).toBe(
      'Test mode: messages reach test numbers only.',
    )
    expect(wrapper.find('.sign-in-channels__setup').exists()).toBe(false)
    expect(wrapper.find(`#${smsBox().attributes('aria-describedby')}`).text()).toBe(
      'Used when the entity signs in with its verified mobile. If SMS fails, the code goes by e-mail.',
    )
    await smsBox().setValue(true)
    await save()
    expect(authSettingsService.update).toHaveBeenCalledWith({
      entitySignInCodeChannels: ['email', 'sms'],
    })
    expect(smsBox().element.checked).toBe(true)
  })

  it('shows no test warning in live mode and sends nothing when nothing changed', async () => {
    await mountScreen({ settings: { smsStatus: 'live' } })
    expect(wrapper.find('.sign-in-channels__warning').exists()).toBe(false)
    expect(smsBox().attributes('disabled')).toBeUndefined()
    await save()
    expect(authSettingsService.update).not.toHaveBeenCalled()
  })

  it('asks the Super Admin for a reason and sends it with the change', async () => {
    await mountScreen({ superAdmin: true })
    await smsBox().setValue(true)
    await save()
    expect(authSettingsService.update).not.toHaveBeenCalled()
    expect(document.body.querySelector('.reason-dialog')).not.toBeNull()
    const textarea = document.body.querySelector('textarea[name="reason"]')
    textarea.value = 'The owner approved SMS sign-in codes in writing.'
    textarea.dispatchEvent(new Event('input'))
    await flushPromises()
    const submit = [...document.body.querySelectorAll('.modal button')].find(
      (button) => button.textContent.trim() === 'Save',
    )
    submit.click()
    await settle()
    expect(authSettingsService.update).toHaveBeenCalledWith({
      entitySignInCodeChannels: ['email', 'sms'],
      reason: 'The owner approved SMS sign-in codes in writing.',
    })
  })

  it('shows the forbidden state for a 403 answer', async () => {
    authSettingsService.get.mockRejectedValue({ normalized: { status: 403, message: 'Forbidden' } })
    const globals = createTestGlobals({ locale: 'en' })
    setActivePinia(globals.pinia)
    wrapper = mount(SignInSecurity, { global: globals, attachTo: document.body })
    await flushPromises()
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.text()).toContain('You do not have access to this screen')
  })

  it('puts a server validation message on the SMS channel', async () => {
    authSettingsService.update.mockRejectedValue({
      normalized: {
        status: 422,
        code: 'validation_failed',
        message: 'The submitted data is invalid.',
        fieldErrors: {
          entitySignInCodeChannels: [
            'Configure the SMS gateway before allowing sign-in codes by text message.',
          ],
        },
      },
    })
    await mountScreen()
    await smsBox().setValue(true)
    await save()
    expect(wrapper.find('.sign-in-channels .field__error').text()).toBe(
      'Configure the SMS gateway before allowing sign-in codes by text message.',
    )
    expect(smsBox().attributes('aria-invalid')).toBe('true')
  })
})
