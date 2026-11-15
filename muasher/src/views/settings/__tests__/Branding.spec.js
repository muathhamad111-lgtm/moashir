import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Branding from '@/views/settings/Branding.vue'
import { siteSettingsService } from '@/services/siteSettingsService'
import { brandingService } from '@/services/brandingService'
import { useAuthStore } from '@/stores/auth'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/siteSettingsService', () => ({
  siteSettingsService: { get: vi.fn(), update: vi.fn() },
}))
vi.mock('@/services/brandingService', () => ({
  brandingService: { get: vi.fn(), update: vi.fn(), reset: vi.fn(), uploadLogo: vi.fn() },
}))

async function mountView(permissions, query = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  useAuthStore(globals.pinia).setUser({ id: 2, roles: ['system_admin'], permissions })
  await globals.router.push({ name: ROUTE_NAMES.ADMIN_BRANDING, query })
  const wrapper = mount(Branding, { global: globals, attachTo: document.body })
  await flushPromises()
  return { wrapper, globals }
}

function tabLabels(wrapper) {
  return wrapper.findAll('.tabs__tab').map((tab) => tab.text())
}

beforeEach(() => {
  vi.clearAllMocks()
  siteSettingsService.get.mockResolvedValue({ data: {} })
  brandingService.get.mockResolvedValue({
    data: { accentToken: 'brand_default', logos: { dark: null }, palette: [], updatedAt: null },
  })
})

describe('Brand identity page', () => {
  it('shows the three tabs to a user who holds both permissions, general first', async () => {
    const { wrapper } = await mountView([
      PERMISSIONS.CONTENT_HOME_MANAGE,
      PERMISSIONS.SETTINGS_BRANDING_MANAGE,
    ])
    expect(wrapper.find('h1').text()).toBe('Brand identity')
    expect(tabLabels(wrapper)).toEqual(['General', 'Visual identity', 'Menus'])
    expect(wrapper.find('#identity-general-heading').exists()).toBe(true)
    wrapper.unmount()
  })

  it('hides the visual identity tab from a content officer', async () => {
    const { wrapper } = await mountView([PERMISSIONS.CONTENT_HOME_MANAGE], { tab: 'visual' })
    expect(tabLabels(wrapper)).toEqual(['General', 'Menus'])
    expect(wrapper.find('.branding-editor').exists()).toBe(false)
    expect(brandingService.get).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('shows only the visual identity tab to a user who manages branding alone', async () => {
    const { wrapper } = await mountView([PERMISSIONS.SETTINGS_BRANDING_MANAGE])
    expect(tabLabels(wrapper)).toEqual(['Visual identity'])
    expect(wrapper.find('.branding-editor').exists()).toBe(true)
    expect(siteSettingsService.get).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('opens the tab named in the address and keeps the choice in it', async () => {
    const { wrapper, globals } = await mountView(
      [PERMISSIONS.CONTENT_HOME_MANAGE, PERMISSIONS.SETTINGS_BRANDING_MANAGE],
      { tab: 'menus' },
    )
    expect(wrapper.find('#identity-menus-heading').exists()).toBe(true)
    await wrapper.findAll('.tabs__tab')[1].trigger('click')
    await flushPromises()
    expect(globals.router.currentRoute.value.query.tab).toBe('visual')
    expect(wrapper.find('.branding-editor').exists()).toBe(true)
    wrapper.unmount()
  })
})
