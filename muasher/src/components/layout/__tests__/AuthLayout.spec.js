import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { createTestGlobals } from '@/test-utils'
import { useUiStore } from '@/stores/ui'

const FORM_SLOT = '<h1 class="auth-form__title">تسجيل الدخول</h1><form class="login-form"></form>'

function mountLayout(globals = createTestGlobals()) {
  return mount(AuthLayout, {
    slots: { default: FORM_SLOT },
    global: globals,
    attachTo: document.body,
  })
}

describe('AuthLayout', () => {
  it('renders the form in main before the brand panel aside', () => {
    const wrapper = mountLayout()
    const main = wrapper.find('main')
    const aside = wrapper.find('aside.auth-layout__brand')
    expect(main.find('.auth-layout__card .login-form').exists()).toBe(true)
    expect(aside.exists()).toBe(true)
    const order = main.element.compareDocumentPosition(aside.element)
    expect(order & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
    wrapper.unmount()
  })

  it('names the aside and keeps only the glowing logo, without the trophy or value cards', () => {
    const wrapper = mountLayout()
    const aside = wrapper.find('aside')
    expect(aside.attributes('aria-label')).toBe('عن منصة مؤشر')
    expect(aside.find('.auth-layout__logo').attributes('alt')).toBe('مؤشر')
    expect(aside.findAll('img')).toHaveLength(1)
    expect(aside.find('.auth-layout__trophy').exists()).toBe(false)
    for (const part of ['halo', 'orbit', 'sheen']) {
      expect(
        aside.find(`.auth-layout__emblem .auth-layout__${part}`).attributes('aria-hidden'),
      ).toBe('true')
    }
    expect(aside.find('.auth-layout__pattern').attributes('aria-hidden')).toBe('true')
    expect(aside.findAll('h2')).toHaveLength(0)
    expect(aside.findAll('li')).toHaveLength(0)
    expect(aside.find('.auth-value-card').exists()).toBe(false)
    expect(wrapper.findAll('h1')).toHaveLength(1)
    wrapper.unmount()
  })

  it('names the aside in English', () => {
    const wrapper = mountLayout(createTestGlobals({ locale: 'en' }))
    expect(wrapper.find('aside').attributes('aria-label')).toBe('About Moashir')
    wrapper.unmount()
  })

  it('offers the language button, the theme switch and a back-to-home link', async () => {
    const globals = createTestGlobals()
    const wrapper = mountLayout(globals)
    const ui = useUiStore(globals.pinia)
    ui.setTheme('light')
    await flushPromises()
    expect(wrapper.find('.lp-top__locale').text()).toBe('EN')
    expect(wrapper.find('.auth-layout__link').text()).toBe('العودة إلى الرئيسية')
    const toggle = wrapper.find('.lp-theme-switch')
    expect(toggle.attributes('role')).toBe('switch')
    await toggle.trigger('click')
    await flushPromises()
    expect(ui.theme).toBe('dark')
    wrapper.unmount()
  })

  it.each([
    [true, true],
    [false, false],
  ])('uses the landing cursor only for a fine pointer (fine: %s)', (fine, expected) => {
    vi.stubGlobal('matchMedia', (query) => ({
      matches: fine,
      media: query,
      addEventListener: () => {},
      removeEventListener: () => {},
    }))
    const wrapper = mountLayout()
    expect(wrapper.classes().includes('lp-has-cursor')).toBe(expected)
    wrapper.unmount()
    vi.unstubAllGlobals()
  })

  it.each([
    [true, 1],
    [false, 0],
  ])(
    'draws the cursor dot field in the brand panel only on the split layout (desktop: %s)',
    (desktop, canvases) => {
      vi.stubGlobal('matchMedia', (query) => ({
        matches: desktop && query.includes('min-width'),
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
      }))
      const wrapper = mountLayout()
      const aside = wrapper.find('aside.auth-layout__brand')
      const dots = aside.findAll('canvas.auth-layout__dots')
      expect(dots).toHaveLength(canvases)
      expect(dots.every((canvas) => canvas.attributes('aria-hidden') === 'true')).toBe(true)
      expect(aside.find('.auth-layout__logo').exists()).toBe(true)
      wrapper.unmount()
      vi.unstubAllGlobals()
    },
  )

  it('keeps the page inside the card viewport that animates its height', () => {
    const wrapper = mountLayout()
    expect(
      wrapper
        .find('.auth-layout__card .auth-layout__viewport .auth-layout__page .login-form')
        .exists(),
    ).toBe(true)
    wrapper.unmount()
  })

  it('drops the back-to-home link on the maintenance page', async () => {
    const globals = createTestGlobals()
    await globals.router.push({ name: ROUTE_NAMES.PUBLIC_MAINTENANCE })
    const wrapper = mountLayout(globals)
    expect(wrapper.find('.auth-layout__link').exists()).toBe(false)
    expect(wrapper.find('aside .auth-layout__logo').exists()).toBe(true)
    wrapper.unmount()
  })
})
