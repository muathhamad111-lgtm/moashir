import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ContactMessages from '@/views/settings/ContactMessages.vue'
import { contactSettingsService } from '@/services/contactSettingsService'
import { changedContactSettings, contactSettingsSchema } from '@/utils/settings/contactSettingsForm'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/contactSettingsService', () => ({
  contactSettingsService: { get: vi.fn(), update: vi.fn() },
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
