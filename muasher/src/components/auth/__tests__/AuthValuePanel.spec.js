import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AuthValuePanel from '@/components/auth/AuthValuePanel.vue'
import { createTestGlobals } from '@/test-utils'

const COPY = {
  ar: {
    titles: ['معايير معلنة وموحّدة', 'تقييم مستقل ومحايد', 'نتائج تقود إلى التحسين'],
    firstBody:
      'نموذج التميّز في العمل الخيري مرجعٌ واحد، تُقاس عليه جميع الجهات بالمعايير والأوزان نفسها.',
  },
  en: {
    titles: [
      'Published, unified standards',
      'Independent, impartial review',
      'Results that drive improvement',
    ],
    firstBody:
      'One reference model. Every entity is measured against the same criteria and weights.',
  },
}

function mountPanel(locale) {
  return mount(AuthValuePanel, { global: createTestGlobals({ locale }) })
}

describe.each(['ar', 'en'])('AuthValuePanel (%s)', (locale) => {
  const copy = COPY[locale]

  it('shows no tagline above the value cards', () => {
    const wrapper = mountPanel(locale)
    expect(wrapper.find('.auth-value-panel__heading').exists()).toBe(false)
    expect(wrapper.find('h1').exists()).toBe(false)
  })

  it('lists the three values as h2 + paragraph cards', () => {
    const wrapper = mountPanel(locale)
    expect(wrapper.find('ul').attributes('role')).toBe('list')
    const cards = wrapper.findAll('li.auth-value-card')
    expect(cards).toHaveLength(3)
    expect(cards.map((card) => card.find('h2').text())).toEqual(copy.titles)
    expect(cards.every((card) => card.find('p').text().length > 0)).toBe(true)
    expect(cards[0].find('p').text()).toBe(copy.firstBody)
  })

  it('draws decorative icons hidden from assistive technology', () => {
    const wrapper = mountPanel(locale)
    const icons = wrapper.findAll('svg')
    expect(icons.map((icon) => icon.attributes('data-icon'))).toEqual([
      'list-check',
      'user-check',
      'chart-line',
    ])
    for (const icon of icons) {
      expect(icon.attributes('aria-hidden')).toBe('true')
      expect(icon.attributes('focusable')).toBe('false')
      expect(icon.attributes('role')).toBeUndefined()
    }
  })

  it('shows no footnote under the value cards', () => {
    const wrapper = mountPanel(locale)
    expect(wrapper.find('.auth-value-panel__footnote').exists()).toBe(false)
    expect(wrapper.findAll('p')).toHaveLength(3)
  })
})
