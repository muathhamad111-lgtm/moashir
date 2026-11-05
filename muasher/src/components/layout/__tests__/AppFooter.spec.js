import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AppFooter from '@/components/layout/AppFooter.vue'
import { usePublicHomeStore } from '@/stores/publicHome'
import { useLegalDocumentsStore } from '@/stores/legalDocuments'
import { createTestGlobals } from '@/test-utils'

const PUBLISHED = { terms: 'available', privacy: 'available', accessibility: 'missing' }

function mountFooter(siteSettings = null, legalStates = PUBLISHED) {
  const globals = createTestGlobals()
  const store = usePublicHomeStore(globals.pinia)
  store.$patch({ status: 'success', siteSettings })
  useLegalDocumentsStore(globals.pinia).$patch({ states: { ...legalStates } })
  return mount(AppFooter, { global: globals })
}

describe('AppFooter', () => {
  it('renders the brand, the description and the copyright line with the year', () => {
    const wrapper = mountFooter()
    expect(wrapper.find('.footer__logo').attributes('alt')).toBe('مؤشر')
    expect(wrapper.find('.footer__tagline').text()).toContain('نموذج التميّز')
    expect(wrapper.find('.footer__copyright').text()).toBe(
      `جميع الحقوق محفوظة © ${new Date().getFullYear()} — منصة مؤشر`,
    )
    expect(wrapper.find('.footer__platform').text()).toContain('Moashir')
  })

  it('falls back to the home section links when no quick links are managed', () => {
    const links = mountFooter().findAll('.footer__nav .footer__link')
    expect(links.map((link) => link.text())).toEqual([
      'جولة في المنصة',
      'مسارات التقييم',
      'نموذج التميّز',
      'مؤشرات الأداء',
      'تواصل معنا',
    ])
    expect(links[0].attributes('href')).toBe('/#tour')
    expect(links[3].attributes('href')).toBe('/kpis')
  })

  it('uses the managed quick links and contact details from the site settings', () => {
    const wrapper = mountFooter({
      quickLinks: [{ label: { ar: 'الرئيسية', en: 'Home' }, url: '/' }],
      contact: { email: 'info@example.sa', phone: '+966 11 000 0000', address: null },
    })
    expect(wrapper.findAll('.footer__nav .footer__link').map((link) => link.text())).toEqual([
      'الرئيسية',
    ])
    expect(wrapper.find('.footer__contact a[href="mailto:info@example.sa"]').exists()).toBe(true)
    expect(wrapper.find('.footer__contact a[href="tel:+966110000000"]').exists()).toBe(true)
  })

  it('links the published legal pages and keeps the others as coming soon', () => {
    const legal = mountFooter().findAll('.footer__legal .footer__soon')
    expect(legal).toHaveLength(3)
    expect(legal[0].text()).toBe('شروط الاستخدام')
    expect(legal[0].attributes('href')).toBe('/terms')
    expect(legal[1].attributes('href')).toBe('/privacy')
    expect(legal[2].element.tagName).toBe('SPAN')
    expect(legal[2].find('a').exists()).toBe(false)
    expect(legal[2].find('.footer__soon-tag').text()).toBe('قريبًا')
  })

  it('is a footer landmark', () => {
    expect(mountFooter().element.tagName).toBe('FOOTER')
  })
})
