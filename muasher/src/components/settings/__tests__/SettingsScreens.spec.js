import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import PathSettingsPanel from '@/components/settings/PathSettingsPanel.vue'
import CertificateSettingsCard from '@/components/settings/paths/CertificateSettingsCard.vue'
import { pricesSettingsService } from '@/services/pricesSettingsService'
import { pathSettingsService } from '@/services/pathSettingsService'
import { certificateSettingsService } from '@/services/certificateSettingsService'
import { toPathSettings } from '@/services/mappers/pathSettings'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/pathSettingsService', () => ({
  pathSettingsService: { get: vi.fn(), update: vi.fn() },
}))
vi.mock('@/services/pricesSettingsService', () => ({
  pricesSettingsService: { get: vi.fn(), history: vi.fn(), update: vi.fn() },
}))
vi.mock('@/services/certificateSettingsService', () => ({
  certificateSettingsService: { get: vi.fn(), update: vi.fn() },
}))

const IDENTITY = {
  description: { ar: 'وصف المسار', en: 'Path description' },
}

function settingsOf(path, isPaid) {
  if (path === 'self') return toPathSettings({ path, ...IDENTITY })
  return toPathSettings({
    path,
    ...IDENTITY,
    reviewSlaLimits: { min: 1, max: 120 },
    reviewSlaDays: 14,
    uploadLimits: { maxFileMb: 20, allowedTypes: ['pdf'], maxFilesPerSub: 5 },
    evidenceTypeCatalog: ['pdf', 'docx'],
    isPaid,
    price: isPaid
      ? {
          priceBeforeVat: '1000.00',
          vatRate: '15.00',
          vatAmount: '150.00',
          total: '1150.00',
          currency: 'SAR',
        }
      : null,
    entityTexts: {},
  })
}

let wrapper

function mountWith(component, props = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(['system_admin'], { id: 1 }))
  wrapper = mount(component, { props, global: globals, attachTo: document.body })
  return wrapper
}

beforeEach(() => {
  pricesSettingsService.get.mockResolvedValue({
    data: { rows: [], limits: {}, vatRate: '15.00' },
  })
  pathSettingsService.get.mockImplementation((path) =>
    Promise.resolve({ data: settingsOf(path, path === 'accredited') }),
  )
  certificateSettingsService.get.mockResolvedValue({
    data: {
      accreditedIssuanceMode: 'automatic',
      levelThresholds: { bronze: '60.00', silver: '70.00', gold: '80.00', diamond: '90.00' },
    },
  })
})

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('per-path settings panel', () => {
  it('edits the description, texts, deadline and uploads of a reviewed path, never its name', async () => {
    mountWith(PathSettingsPanel, { path: 'accredited' })
    await flushPromises()
    expect(pathSettingsService.get).toHaveBeenCalledWith('accredited')
    expect(wrapper.find('input[name^="name."]').exists()).toBe(false)
    expect(wrapper.find('textarea[name="description.en"]').element.value).toBe('Path description')
    expect(wrapper.find('input[name="reviewSlaDays"]').exists()).toBe(true)
    expect(wrapper.findAll('textarea[name^="entityTexts."]')).toHaveLength(6)
    expect(wrapper.find('.path-settings__intro').exists()).toBe(false)
    expect(wrapper.findAll('input[name="accreditedIssuanceMode"]')).toHaveLength(2)
  })

  it('sends only the changed description of a path', async () => {
    pathSettingsService.update.mockImplementation((path, payload) =>
      Promise.resolve({ data: settingsOf(path, false), payload }),
    )
    mountWith(PathSettingsPanel, { path: 'advanced' })
    await flushPromises()
    await wrapper.find('textarea[name="description.en"]').setValue('The annual award')
    await wrapper.find('form.settings-form').trigger('submit')
    await vi.waitFor(() =>
      expect(pathSettingsService.update).toHaveBeenCalledWith('advanced', {
        description: { ar: 'وصف المسار', en: 'The annual award' },
      }),
    )
  })

  it('manages only the description of the self assessment, with no certificates', async () => {
    mountWith(PathSettingsPanel, { path: 'self' })
    await flushPromises()
    expect(wrapper.find('textarea[name="description.ar"]').exists()).toBe(true)
    expect(wrapper.find('input[name="reviewSlaDays"]').exists()).toBe(false)
    expect(wrapper.find('textarea[name^="entityTexts."]').exists()).toBe(false)
    expect(wrapper.text()).toContain('This path issues no certificates for now.')
  })

  it('offers a retry when the settings cannot be read', async () => {
    pathSettingsService.get.mockRejectedValueOnce({
      normalized: { status: 500, message: 'Failed' },
    })
    mountWith(PathSettingsPanel, { path: 'advanced' })
    await flushPromises()
    expect(wrapper.find('.error-state').exists()).toBe(true)
    await wrapper.find('.error-state button').trigger('click')
    await flushPromises()
    expect(wrapper.find('input[name="reviewSlaDays"]').exists()).toBe(true)
  })
})

describe('certificate settings', () => {
  it('shows the four thresholds and the resulting levels in the general card', async () => {
    mountWith(CertificateSettingsCard, { part: 'levels' })
    await flushPromises()
    expect(wrapper.find('.toggle-switch').exists()).toBe(false)
    expect(wrapper.findAll('input[name^="levelThresholds."]')).toHaveLength(4)
    const ranges = wrapper.findAll('.level-ranges__item')
    expect(ranges).toHaveLength(5)
    expect(ranges[0].text()).toContain('Below')
    expect(ranges[4].text()).toContain('and above')
  })

  it('shows only the issuance mode choice in the accredited card', async () => {
    mountWith(CertificateSettingsCard, { part: 'issuance' })
    await flushPromises()
    expect(wrapper.findAll('input[name="accreditedIssuanceMode"]')).toHaveLength(2)
    expect(wrapper.text()).toContain('Automatic when the assessment is completed')
    expect(wrapper.find('input[name^="levelThresholds."]').exists()).toBe(false)
  })
})
