import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AuthValuePanel from '@/components/auth/AuthValuePanel.vue'
import { createTestGlobals } from '@/test-utils'

const HEADING_ID = 'auth-panel-heading'

const COPY = {
  ar: {
    heading: 'قياسٌ عادل، وتحسينٌ مستمر',
    titles: ['معايير معلنة وموحّدة', 'تقييم مستقل ومحايد', 'نتائج تقود إلى التحسين'],
    firstBody:
      'نموذج التميّز في العمل الخيري مرجعٌ واحد، تُقاس عليه جميع الجهات بالمعايير والأوزان نفسها.',
    footnote: 'بياناتك محمية وفق ضوابط الهيئة الوطنية للأمن السيبراني.',
  },
  en: {
    heading: 'Fair measurement, continuous improvement',
    titles: [
      'Published, unified standards',
      'Independent, impartial review',
      'Results that drive improvement',
    ],
    firstBody:
      'One reference model. Every entity is measured against the same criteria and weights.',
    footnote: 'Your data is protected in line with National Cybersecurity Authority controls.',
  },
}

function mountPanel(locale, props = {}) {
  return mount(AuthValuePanel, {
    props: { headingId: HEADING_ID, ...props },
    global: createTestGlobals({ locale }),
  })
}

describe.each(['ar', 'en'])('AuthValuePanel (%s)', (locale) => {
  const copy = COPY[locale]

  it('renders one h2 heading carrying the id that names the panel', () => {
    const wrapper = mountPanel(locale)
    const headings = wrapper.findAll('h2')
    expect(headings).toHaveLength(1)
    expect(headings[0].attributes('id')).toBe(HEADING_ID)
    expect(headings[0].text()).toBe(copy.heading)
    expect(wrapper.find('h1').exists()).toBe(false)
  })

  it('lists the three values as h3 + paragraph cards', () => {
    const wrapper = mountPanel(locale)
    expect(wrapper.find('ul').attributes('role')).toBe('list')
    const cards = wrapper.findAll('li.auth-value-card')
    expect(cards).toHaveLength(3)
    expect(cards.map((card) => card.find('h3').text())).toEqual(copy.titles)
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
      'lock',
    ])
    for (const icon of icons) {
      expect(icon.attributes('aria-hidden')).toBe('true')
      expect(icon.attributes('focusable')).toBe('false')
      expect(icon.attributes('role')).toBeUndefined()
    }
  })

  it('shows the footnote by default and hides it when the flag is off', async () => {
    const wrapper = mountPanel(locale)
    expect(wrapper.find('.auth-value-panel__footnote').text()).toBe(copy.footnote)
    await wrapper.setProps({ showFootnote: false })
    expect(wrapper.find('.auth-value-panel__footnote').exists()).toBe(false)
    expect(wrapper.text()).not.toContain(copy.footnote)
  })
})
