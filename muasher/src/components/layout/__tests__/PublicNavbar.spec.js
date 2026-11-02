import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PublicNavbar from '@/components/layout/PublicNavbar.vue'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'

describe('PublicNavbar', () => {
  it('renders active public links (home, KPIs, lenses) and a login CTA for guests', () => {
    const wrapper = mount(PublicNavbar, { global: createTestGlobals() })
    const links = wrapper.findAll('.public-navbar__link').map((el) => el.text())
    expect(links[0]).toBe('الرئيسية')
    expect(links[1]).toBe('مؤشرات الأداء')
    expect(links[2]).toContain('العدسات التحليلية')
    expect(links[2]).not.toContain('قريبًا')
    expect(wrapper.text()).toContain('تسجيل الدخول')
  })

  it('shows the dashboard CTA instead of login when authenticated', async () => {
    const globals = createTestGlobals()
    const auth = useAuthStore(globals.pinia)
    auth.setUser({ id: 1, roles: [], permissions: [] })
    const wrapper = mount(PublicNavbar, { global: globals })
    expect(wrapper.text()).toContain('لوحة التحكم')
    expect(wrapper.text()).not.toContain('تسجيل الدخول')
  })

  it('toggles the mobile menu with aria wiring', async () => {
    const wrapper = mount(PublicNavbar, { global: createTestGlobals() })
    const toggle = wrapper.find('.public-navbar__toggle')
    expect(toggle.attributes('aria-expanded')).toBe('false')
    await toggle.trigger('click')
    expect(toggle.attributes('aria-expanded')).toBe('true')
    expect(wrapper.find('.public-navbar__menu').classes()).toContain('is-open')
  })
})
