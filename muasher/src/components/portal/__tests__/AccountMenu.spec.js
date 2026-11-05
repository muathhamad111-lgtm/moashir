import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AccountMenu from '@/components/portal/AccountMenu.vue'
import { useAuthStore } from '@/stores/auth'
import { authService } from '@/services/authService'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/authService', async (importOriginal) => {
  const original = await importOriginal()
  return { ...original, authService: { ...original.authService, logout: vi.fn() } }
})
vi.mock('@/services/profileService', () => ({
  profileService: { updatePreferences: vi.fn(() => Promise.resolve({ data: {} })) },
}))

function factory() {
  const globals = createTestGlobals()
  useAuthStore(globals.pinia).setUser(
    sessionUserFor(['entity'], {
      name: 'سارة عبدالله',
      email: 'sara@alamal.example',
      entity: { id: 5001, name: { ar: 'جمعية الأمل النموذجية', en: 'Al-Amal Model Association' } },
    }),
  )
  const wrapper = mount(AccountMenu, { global: globals, attachTo: document.body })
  return { wrapper, globals }
}

beforeEach(() => {
  vi.clearAllMocks()
  authService.logout.mockResolvedValue({})
})

describe('AccountMenu', () => {
  it('shows the entity and the user on the chip', () => {
    const { wrapper } = factory()
    expect(wrapper.find('.account-menu__entity').text()).toBe('جمعية الأمل النموذجية')
    expect(wrapper.find('.account-menu__user').text()).toBe('سارة عبدالله')
    expect(wrapper.find('.account-menu__panel').exists()).toBe(false)
    wrapper.unmount()
  })

  it('opens an overlay with the identity, moves focus inside and closes with Escape', async () => {
    const { wrapper } = factory()
    const chip = wrapper.find('.account-menu__chip')
    chip.element.focus()
    await chip.trigger('click')
    await flushPromises()
    const panel = wrapper.find('.account-menu__panel')
    expect(chip.attributes('aria-expanded')).toBe('true')
    expect(panel.attributes('role')).toBe('dialog')
    expect(panel.text()).toContain('مسؤول حساب الجهة')
    expect(panel.find('bdi').text()).toBe('sara@alamal.example')
    expect(panel.element.contains(document.activeElement)).toBe(true)
    await panel.trigger('keydown', { key: 'Escape' })
    await flushPromises()
    expect(wrapper.find('.account-menu__panel').exists()).toBe(false)
    expect(document.activeElement).toBe(chip.element)
    wrapper.unmount()
  })

  it('keeps Tab inside the overlay', async () => {
    const { wrapper } = factory()
    await wrapper.find('.account-menu__chip').trigger('click')
    await flushPromises()
    const entries = wrapper.findAll('.portal-menu-entry')
    entries.at(-1).element.focus()
    await wrapper.find('.account-menu__panel').trigger('keydown', { key: 'Tab' })
    expect(document.activeElement).toBe(entries[0].element)
    wrapper.unmount()
  })

  it('lists profile, organization profile, language, theme, help and sign out', async () => {
    const { wrapper } = factory()
    await wrapper.find('.account-menu__chip').trigger('click')
    const labels = wrapper.findAll('.portal-menu-entry__label').map((entry) => entry.text())
    expect(labels).toEqual([
      'ملفي الشخصي',
      'ملف الجهة',
      'اللغة',
      'المظهر',
      'المساعدة والأدلة',
      'تسجيل الخروج',
    ])
    expect(wrapper.findAll('.portal-menu-entry')[0].attributes('href')).toBe('/account')
    expect(wrapper.findAll('.portal-menu-entry')[4].attributes('href')).toBe('/help')
    const language = wrapper.findAll('.portal-menu-entry')[2]
    expect(language.find('svg').exists()).toBe(false)
    expect(language.find('span.portal-menu-entry__icon').attributes('aria-hidden')).toBe('true')
    expect(wrapper.findAll('.portal-menu-entry')[3].find('svg').exists()).toBe(true)
    wrapper.unmount()
  })

  it('signs out and returns to the sign-in page', async () => {
    const { wrapper, globals } = factory()
    const push = vi.spyOn(globals.router, 'push')
    await wrapper.find('.account-menu__chip').trigger('click')
    await wrapper.findAll('.portal-menu-entry').at(-1).trigger('click')
    await flushPromises()
    expect(authService.logout).toHaveBeenCalled()
    expect(useAuthStore().isAuthenticated).toBe(false)
    expect(push).toHaveBeenCalledWith({ name: 'auth.login' })
    wrapper.unmount()
  })
})
