import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import ToolTeaser from '@/components/portal/ToolTeaser.vue'
import { PORTAL_TOOLS } from '@/constants/portal'

function render(toolKey, locale = 'ar') {
  return mount(ToolTeaser, { props: { toolKey }, global: createTestGlobals({ locale }) })
}

describe('ToolTeaser', () => {
  it.each(PORTAL_TOOLS.map((tool) => tool.key))(
    'renders the %s teaser in both languages',
    (key) => {
      const ar = render(key)
      const en = render(key, 'en')
      expect(ar.find('.tool-teaser__title').text()).not.toContain('portal.tools')
      expect(en.find('.tool-teaser__title').text()).not.toContain('portal.tools')
      expect(ar.findAll('.tool-teaser__benefit')).toHaveLength(3)
      expect(ar.find('.coming-soon-chip').text()).toBe('قريباً')
      expect(en.find('.coming-soon-chip').text()).toBe('Soon')
    },
  )

  it('names the source and links back to the journey', () => {
    const wrapper = render('strengths')
    expect(wrapper.find('.tool-teaser__title').text()).toBe('القوة والفجوات')
    expect(wrapper.find('.tool-teaser__source').text()).toContain('التقييم المعتمد')
    expect(wrapper.find('.tool-teaser__back').attributes('href')).toBe('/dashboard')
  })
})
