import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ContactMessages from '@/views/settings/ContactMessages.vue'
import ContactPrivacyTools from '@/components/entities/admin/ContactPrivacyTools.vue'
import ConfirmModal from '@/components/modal/ConfirmModal.vue'
import { contactSettingsService } from '@/services/contactSettingsService'
import { contactPrivacyService } from '@/services/contactPrivacyService'
import { changedContactSettings, contactSettingsSchema } from '@/utils/settings/contactSettingsForm'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/contactSettingsService', () => ({
  contactSettingsService: { get: vi.fn(), update: vi.fn() },
}))
vi.mock('@/services/contactPrivacyService', () => ({
  contactPrivacyService: { exportByEmail: vi.fn(), eraseByEmail: vi.fn() },
}))
vi.mock('@/utils/download', () => ({ downloadJson: vi.fn(), downloadBlob: vi.fn() }))

const SETTINGS = { retentionMonths: 24, retentionLimits: { min: 1, max: 120 } }
const t = (key, params) => `${key}${params ? JSON.stringify(params) : ''}`

beforeEach(() => {
  vi.clearAllMocks()
  contactSettingsService.get.mockResolvedValue({ data: SETTINGS })
  contactSettingsService.update.mockResolvedValue({ data: { ...SETTINGS, retentionMonths: 12 } })
})

describe('contact retention form rules', () => {
  it('accepts whole months inside the server bounds only', async () => {
    const schema = contactSettingsSchema(t, SETTINGS)
    await expect(schema.validate({ retentionMonths: '12' })).resolves.toEqual({
      retentionMonths: 12,
    })
    await expect(schema.validate({ retentionMonths: '٣٦' })).resolves.toEqual({
      retentionMonths: 36,
    })
    await expect(schema.validate({ retentionMonths: '0' })).rejects.toThrow(
      'settings.contactMessages.validation.whole',
    )
    await expect(schema.validate({ retentionMonths: '121' })).rejects.toThrow(
      'settings.contactMessages.validation.whole',
    )
    await expect(schema.validate({ retentionMonths: '2.5' })).rejects.toThrow(
      'settings.contactMessages.validation.whole',
    )
  })

  it('sends only a changed value', () => {
    expect(changedContactSettings(SETTINGS, { retentionMonths: '24' })).toEqual({})
    expect(changedContactSettings(SETTINGS, { retentionMonths: '12' })).toEqual({
      retentionMonths: 12,
    })
  })
})

describe('contact retention screen', () => {
  it('saves a new retention period', async () => {
    const globals = createTestGlobals({ locale: 'en' })
    useAuthStore(globals.pinia).setUser(sessionUserFor(['system_admin']))
    const wrapper = mount(ContactMessages, { global: globals, attachTo: document.body })
    await flushPromises()
    expect(wrapper.text()).toContain('A whole number from 1 to 120 months.')
    await wrapper.find('input[name="retentionMonths"]').setValue('12')
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() =>
      expect(contactSettingsService.update).toHaveBeenCalledWith({ retentionMonths: 12 }),
    )
    wrapper.unmount()
  })
})

describe('contact privacy tools', () => {
  function mountTools() {
    const globals = createTestGlobals({ locale: 'en' })
    return mount(
      {
        components: { ContactPrivacyTools, ConfirmModal },
        template: '<ContactPrivacyTools /><ConfirmModal />',
      },
      { global: globals, attachTo: document.body },
    )
  }

  it('validates the address and the reason before calling the server', async () => {
    const wrapper = mountTools()
    await wrapper.find('form').trigger('submit')
    expect(contactPrivacyService.exportByEmail).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Enter a valid e-mail address.')
    wrapper.unmount()
  })

  it('exports the messages of an address and lists their references', async () => {
    contactPrivacyService.exportByEmail.mockResolvedValue({
      data: { messages: [{ reference: 'CNT-2026-000044' }] },
    })
    const wrapper = mountTools()
    await wrapper.find('input[type="email"]').setValue('sara@example.sa')
    await wrapper.find('textarea').setValue('طلب صاحب البيانات رقم 12')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(contactPrivacyService.exportByEmail).toHaveBeenCalledWith({
      email: 'sara@example.sa',
      reason: 'طلب صاحب البيانات رقم 12',
    })
    expect(wrapper.text()).toContain('Messages exported: 1')
    expect(wrapper.text()).toContain('CNT-2026-000044')
    wrapper.unmount()
  })

  it('erases only after the danger confirmation', async () => {
    contactPrivacyService.eraseByEmail.mockResolvedValue({
      data: { erasedMessages: 1, erasedEntries: 2 },
    })
    const wrapper = mountTools()
    await wrapper.find('input[type="email"]').setValue('sara@example.sa')
    await wrapper.find('textarea').setValue('طلب صاحب البيانات رقم 12')
    const erase = wrapper
      .findAll('button')
      .find((button) => button.text() === 'Delete the messages')
    await erase.trigger('click')
    await flushPromises()
    expect(contactPrivacyService.eraseByEmail).not.toHaveBeenCalled()
    const accept = [...document.querySelectorAll('.modal button')].find((button) =>
      button.textContent.includes('Delete the messages'),
    )
    accept.click()
    await flushPromises()
    expect(contactPrivacyService.eraseByEmail).toHaveBeenCalled()
    expect(wrapper.text()).toContain('1 messages and 2 entries were deleted.')
    wrapper.unmount()
  })
})
