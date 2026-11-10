import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import PathSettingsPanel from '@/components/settings/PathSettingsPanel.vue'
import Certificates from '@/views/settings/Certificates.vue'
import { pathSettingsService } from '@/services/pathSettingsService'
import { certificateSettingsService } from '@/services/certificateSettingsService'
import { toPathSettings } from '@/services/mappers/pathSettings'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/pathSettingsService', () => ({
  pathSettingsService: { get: vi.fn(), update: vi.fn() },
}))
vi.mock('@/services/certificateSettingsService', () => ({
  certificateSettingsService: { get: vi.fn(), update: vi.fn() },
}))

function settingsOf(path, isPaid) {
  return toPathSettings({
    path,
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
  pathSettingsService.get.mockImplementation((path) =>
    Promise.resolve({ data: settingsOf(path, path === 'accredited') }),
  )
  certificateSettingsService.get.mockResolvedValue({
    data: {
      accreditedIssuanceEnabled: true,
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
  it('shows the price read-only on the paid path and points to the path prices', async () => {
    mountWith(PathSettingsPanel, { path: 'accredited' })
    await flushPromises()
    expect(pathSettingsService.get).toHaveBeenCalledWith('accredited')
    expect(wrapper.find('input[name="price"]').exists()).toBe(false)
    expect(wrapper.find('.pricing-breakdown').text()).toContain('1,150.00')
    expect(wrapper.find('[data-testid="path-price-moved"]').exists()).toBe(true)
    expect(wrapper.findAll('textarea')).toHaveLength(6)
    wrapper.unmount()
    mountWith(PathSettingsPanel, { path: 'advanced' })
    await flushPromises()
    expect(wrapper.find('[data-testid="path-price-moved"]').exists()).toBe(false)
    expect(wrapper.find('.path-settings__intro .path-badge--advanced').exists()).toBe(true)
  })

  it('keeps the price section and its link on the accredited path while no price is in force', async () => {
    pathSettingsService.get.mockResolvedValueOnce({ data: settingsOf('accredited', false) })
    mountWith(PathSettingsPanel, { path: 'accredited' })
    await flushPromises()
    expect(wrapper.find('.pricing-breakdown').exists()).toBe(false)
    expect(wrapper.text()).toContain('No payment is requested for this path now')
    expect(wrapper.find('[data-testid="path-price-moved"] a').attributes('href')).toBe(
      '/admin/settings/prices',
    )
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
  it('shows the issuance switch, the four thresholds and the resulting levels', async () => {
    mountWith(Certificates)
    await flushPromises()
    expect(wrapper.find('.toggle-switch').exists()).toBe(true)
    expect(wrapper.findAll('input[name^="levelThresholds."]')).toHaveLength(4)
    const ranges = wrapper.findAll('.level-ranges__item')
    expect(ranges).toHaveLength(5)
    expect(ranges[0].text()).toContain('Below')
    expect(ranges[4].text()).toContain('and above')
    expect(wrapper.find('.certificate-editor').exists()).toBe(false)
  })
})
