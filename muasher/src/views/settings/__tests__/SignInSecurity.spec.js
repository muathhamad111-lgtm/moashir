import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import SignInSecurity from '@/views/settings/SignInSecurity.vue'
import { authSettingsService } from '@/services/authSettingsService'
import { useConfirm } from '@/composables/useConfirm'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/authSettingsService', () => ({
  authSettingsService: { get: vi.fn(), update: vi.fn() },
}))
vi.mock('@/composables/useConfirm', () => {
  const confirm = vi.fn()
  return { useConfirm: () => ({ confirm }) }
})

const SETTINGS = {
  entitySecondFactorEnabled: true,
  entitySecondFactorChannel: 'email',
  staffTotpRequired: true,
}

let wrapper

async function mountScreen({ superAdmin = false, locale = 'en' } = {}) {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(
    sessionUserFor(superAdmin ? [] : ['system_admin'], { id: 1, isSuperAdmin: superAdmin }),
  )
  wrapper = mount(SignInSecurity, { global: globals, attachTo: document.body })
  await flushPromises()
  return wrapper
}

async function switchOff() {
  await wrapper.find('input[name="entitySecondFactorEnabled"]').setValue(false)
  await flushPromises()
}

async function save() {
  await wrapper.find('form').trigger('submit')
  for (let round = 0; round < 3; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

beforeEach(() => {
  authSettingsService.get.mockResolvedValue({ data: { ...SETTINGS } })
  authSettingsService.update.mockImplementation((payload) =>
    Promise.resolve({ data: { ...SETTINGS, ...payload } }),
  )
  useConfirm().confirm.mockResolvedValue(true)
})

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('sign-in security setting', () => {
  it('shows the entity switch on and what always applies', async () => {
    await mountScreen()
    expect(authSettingsService.get).toHaveBeenCalled()
    expect(wrapper.find('input[name="entitySecondFactorEnabled"]').element.checked).toBe(true)
    expect(wrapper.text()).toContain('the password alone is not enough')
    expect(wrapper.findAll('.sign-in-security__fact')).toHaveLength(3)
    expect(wrapper.find('.second-factor-risk').exists()).toBe(false)
  })

  it('explains the risk when switched off and asks before saving it', async () => {
    await mountScreen()
    await switchOff()
    const risk = wrapper.find('.second-factor-risk')
    expect(risk.attributes('role')).toBe('alert')
    expect(risk.text()).toContain('password alone')
    expect(risk.text()).toContain('Essential Cybersecurity Controls')
    await save()
    expect(useConfirm().confirm).toHaveBeenCalledWith(
      expect.objectContaining({ variant: 'danger' }),
    )
    expect(authSettingsService.update).toHaveBeenCalledWith({ entitySecondFactorEnabled: false })
  })

  it('does not save when the administrator cancels the confirmation', async () => {
    useConfirm().confirm.mockResolvedValue(false)
    await mountScreen()
    await switchOff()
    await save()
    expect(authSettingsService.update).not.toHaveBeenCalled()
  })

  it('asks the Super Admin for a reason and sends it with the change', async () => {
    await mountScreen({ superAdmin: true })
    await switchOff()
    await save()
    expect(authSettingsService.update).not.toHaveBeenCalled()
    const dialog = document.body.querySelector('.reason-dialog')
    expect(dialog).not.toBeNull()
    const textarea = document.body.querySelector('textarea[name="reason"]')
    textarea.value = 'The client accepted the deviation in writing.'
    textarea.dispatchEvent(new Event('input'))
    await flushPromises()
    const submit = [...document.body.querySelectorAll('.modal button')].find(
      (button) => button.textContent.trim() === 'Save',
    )
    submit.click()
    for (let round = 0; round < 3; round += 1) {
      await new Promise((resolve) => setTimeout(resolve, 10))
      await flushPromises()
    }
    expect(authSettingsService.update).toHaveBeenCalledWith({
      entitySecondFactorEnabled: false,
      reason: 'The client accepted the deviation in writing.',
    })
  })

  it('shows the forbidden state for a 403 answer', async () => {
    authSettingsService.get.mockRejectedValue({ normalized: { status: 403, message: 'Forbidden' } })
    await mountScreen()
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.text()).toContain('You do not have access to this screen')
  })

  it('puts a server validation message on the switch', async () => {
    authSettingsService.update.mockRejectedValue({
      normalized: {
        status: 422,
        code: 'validation_failed',
        message: 'The submitted data is invalid.',
        fieldErrors: { entitySecondFactorEnabled: ['The field must be true or false.'] },
      },
    })
    await mountScreen()
    await switchOff()
    await save()
    expect(wrapper.text()).toContain('The field must be true or false.')
  })
})
