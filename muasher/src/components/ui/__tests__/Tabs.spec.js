import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import Tabs from '@/components/ui/Tabs.vue'

const tabs = [
  { value: 'current', label: 'التقييم الحالي' },
  { value: 'previous', label: 'التقييمات السابقة' },
  { value: 'archive', label: 'الأرشيف', disabled: true },
]

function factory(modelValue = 'current') {
  return mount(Tabs, {
    props: { modelValue, tabs },
    slots: { default: '<p>panel-{{ params.active }}</p>' },
  })
}

describe('Tabs', () => {
  it('renders the tablist with the active tab selected and its panel wired', () => {
    const wrapper = factory()
    const tabButtons = wrapper.findAll('[role="tab"]')
    expect(tabButtons).toHaveLength(3)
    expect(tabButtons[0].attributes('aria-selected')).toBe('true')
    expect(tabButtons[0].classes()).toContain('is-active')
    const panel = wrapper.find('[role="tabpanel"]')
    expect(panel.text()).toBe('panel-current')
    expect(panel.attributes('aria-labelledby')).toBe(tabButtons[0].attributes('id'))
  })

  it('emits update:modelValue on click and ignores disabled tabs', async () => {
    const wrapper = factory()
    const tabButtons = wrapper.findAll('[role="tab"]')
    await tabButtons[1].trigger('click')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['previous'])
    await tabButtons[2].trigger('click')
    expect(wrapper.emitted('update:modelValue')).toHaveLength(1)
  })

  it('moves selection with arrow keys (roving tabindex)', async () => {
    const wrapper = factory()
    const tabButtons = wrapper.findAll('[role="tab"]')
    expect(tabButtons[1].attributes('tabindex')).toBe('-1')
    await tabButtons[0].trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['previous'])
  })
})
