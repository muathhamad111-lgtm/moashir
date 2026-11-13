import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import PublicNavbar from '@/components/layout/PublicNavbar.vue'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'

let wrapper = null

function mountNavbar(globals = createTestGlobals()) {
  wrapper = mount(PublicNavbar, { global: globals, attachTo: document.body })
  return wrapper
}

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
})

describe('PublicNavbar', () => {
  it('renders the approved section links, the KPIs page link and guest actions', () => {
    mountNavbar()
    const links = wrapper.findAll('.public-navbar__menu .public-nav-links__link')
    expect(links.map((link) => link.text())).toEqual([
      'الرئيسية',
      'جولة في المنصة',
      'مسارات التقييم',
      'نموذج التميّز',
      'مؤشرات الأداء',
      'تواصل معنا',
    ])
    expect(links[2].attributes('href')).toBe('/#paths')
    expect(links[4].attributes('href')).toBe('/kpis')
    expect(wrapper.find('.public-navbar__login-full').text()).toBe('تسجيل الدخول')
    expect(wrapper.text()).toContain('إنشاء حساب جهة')
  })

  it('shows the dashboard action instead of sign-in when authenticated', () => {
    const globals = createTestGlobals()
    useAuthStore(globals.pinia).setUser({ id: 1, roles: [], permissions: [] })
    mountNavbar(globals)
    expect(wrapper.text()).toContain('لوحة التحكم')
    expect(wrapper.find('.public-navbar__login').exists()).toBe(false)
  })

  it('opens the drawer as a modal dialog, traps focus and closes with Escape', async () => {
    mountNavbar()
    const toggle = wrapper.find('.public-navbar__toggle')
    expect(toggle.attributes('aria-expanded')).toBe('false')
    await toggle.trigger('click')
    await nextTick()
    expect(toggle.attributes('aria-expanded')).toBe('true')
    const dialog = document.querySelector('.nav-drawer__panel')
    expect(dialog.getAttribute('role')).toBe('dialog')
    expect(dialog.getAttribute('aria-modal')).toBe('true')
    expect(document.activeElement).toBe(dialog.querySelector('.nav-drawer__close'))

    const focusable = dialog.querySelectorAll('a[href], button:not([disabled])')
    focusable[focusable.length - 1].focus()
    document
      .querySelector('.nav-drawer')
      .dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }))
    expect(document.activeElement).toBe(focusable[0])

    document
      .querySelector('.nav-drawer')
      .dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await nextTick()
    await nextTick()
    expect(document.querySelector('.nav-drawer')).toBeNull()
    expect(toggle.attributes('aria-expanded')).toBe('false')
  })

  it('closes the drawer from the scrim', async () => {
    mountNavbar()
    await wrapper.find('.public-navbar__toggle').trigger('click')
    await nextTick()
    document.querySelector('.nav-drawer__scrim').click()
    await nextTick()
    await nextTick()
    expect(document.querySelector('.nav-drawer')).toBeNull()
  })
})
