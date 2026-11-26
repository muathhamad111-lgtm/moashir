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

  it('opens a labelled dialog with the identity, moves focus inside and closes with Escape', async () => {
    const { wrapper } = factory()
    const chip = wrapper.find('.account-menu__chip')
    chip.element.focus()
    await chip.trigger('click')
    await flushPromises()
    const dialog = wrapper.find('[role="dialog"]')
    expect(chip.attributes('aria-expanded')).toBe('true')
    expect(chip.attributes('aria-haspopup')).toBe('dialog')
    expect(chip.attributes('aria-controls')).toBe(dialog.attributes('id'))
    const heading = wrapper.find(`#${dialog.attributes('aria-labelledby')}`)
    expect(heading.text()).toBe('قائمة الحساب')
    expect(dialog.text()).toContain('مسؤول حساب الجهة')
    expect(dialog.find('bdi').text()).toBe('sara@alamal.example')
    expect(document.activeElement).toBe(heading.element)
    await dialog.trigger('keydown', { key: 'Escape' })
    await flushPromises()
    expect(wrapper.find('.account-menu__panel').exists()).toBe(false)
    expect(document.activeElement).toBe(chip.element)
    wrapper.unmount()
  })

  it('closes when the focus leaves the panel', async () => {
    const { wrapper } = factory()
    const outside = document.createElement('button')
    document.body.appendChild(outside)
    await wrapper.find('.account-menu__chip').trigger('click')
    await flushPromises()
    await wrapper.find('[role="dialog"]').trigger('focusout', { relatedTarget: outside })
    expect(wrapper.find('.account-menu__panel').exists()).toBe(false)
    outside.remove()
    wrapper.unmount()
  })

  it('lists the entity profile, help and sign out', async () => {
    const { wrapper } = factory()
    await wrapper.find('.account-menu__chip').trigger('click')
    const labels = wrapper.findAll('.portal-menu-entry__label').map((entry) => entry.text())
    expect(labels).toEqual(['ملف الجهة', 'المساعدة والأدلة', 'تسجيل الخروج'])
    expect(wrapper.findAll('.portal-menu-entry')[0].attributes('href')).toBe('/entity/profile')
    expect(wrapper.findAll('.portal-menu-entry')[1].attributes('href')).toBe('/help')
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
