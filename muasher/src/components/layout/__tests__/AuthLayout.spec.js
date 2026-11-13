import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { createTestGlobals } from '@/test-utils'

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

  it('names the aside with the panel h2 and keeps the form h1 the only h1', () => {
    const wrapper = mountLayout()
    const aside = wrapper.find('aside')
    const heading = aside.find('h2')
    expect(heading.text()).toBe('قياسٌ عادل، وتحسينٌ مستمر')
    expect(heading.attributes('id')).toBeTruthy()
    expect(aside.attributes('aria-labelledby')).toBe(heading.attributes('id'))
    expect(document.getElementById(heading.attributes('id'))).toBe(heading.element)
    expect(wrapper.findAll('h1')).toHaveLength(1)
    expect(wrapper.find('main h1').exists()).toBe(true)
    expect(aside.findAll('h3')).toHaveLength(3)
    wrapper.unmount()
  })

  it('shows the on-dark logo over the lattice and no illustration or tagline', () => {
    const wrapper = mountLayout()
    const aside = wrapper.find('aside')
    expect(aside.find('.auth-layout__logo').attributes('alt')).toBe('مؤشر')
    expect(aside.find('.brand-pattern--lattice').exists()).toBe(true)
    expect(aside.find('.illustration-slot').exists()).toBe(false)
    expect(aside.find('.auth-layout__tagline').exists()).toBe(false)
    expect(aside.text()).not.toContain('بوابة التقييم المؤسسي')
    wrapper.unmount()
  })

  it('renders the English panel copy in English', () => {
    const wrapper = mountLayout(createTestGlobals({ locale: 'en' }))
    expect(wrapper.find('aside h2').text()).toBe('Fair measurement, continuous improvement')
    expect(wrapper.find('aside .auth-value-panel__footnote').exists()).toBe(false)
    wrapper.unmount()
  })

  it('offers locale/theme switchers and a back-to-home link', () => {
    const wrapper = mountLayout()
    expect(wrapper.find('.auth-layout__meta').text()).toContain('English')
    expect(wrapper.find('.auth-layout__link').text()).toBe('العودة إلى الرئيسية')
    wrapper.unmount()
  })

  it('drops the back-to-home link on the maintenance page', async () => {
    const globals = createTestGlobals()
    await globals.router.push({ name: ROUTE_NAMES.PUBLIC_MAINTENANCE })
    const wrapper = mountLayout(globals)
    expect(wrapper.find('.auth-layout__link').exists()).toBe(false)
    expect(wrapper.find('aside h2').exists()).toBe(true)
    wrapper.unmount()
  })
})
