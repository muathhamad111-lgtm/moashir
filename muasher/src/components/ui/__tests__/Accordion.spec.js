import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import Accordion from '@/components/ui/Accordion.vue'

const items = [
  { id: 'a', title: 'معيار القيادة', content: 'وصف القيادة' },
  { id: 'b', title: 'معيار الاستراتيجية', content: 'وصف الاستراتيجية' },
]

describe('Accordion', () => {
  it('starts closed and toggles a panel with aria wiring', async () => {
    const wrapper = mount(Accordion, { props: { items } })
    expect(wrapper.find('.accordion__panel').exists()).toBe(false)
    const trigger = wrapper.findAll('.accordion__trigger')[0]
    expect(trigger.attributes('aria-expanded')).toBe('false')
    await trigger.trigger('click')
    expect(trigger.attributes('aria-expanded')).toBe('true')
    const panel = wrapper.find('.accordion__panel')
    expect(panel.text()).toBe('وصف القيادة')
    expect(panel.attributes('aria-labelledby')).toBe(trigger.attributes('id'))
  })

  it('closes the open item when another opens (single mode)', async () => {
    const wrapper = mount(Accordion, { props: { items } })
    const triggers = wrapper.findAll('.accordion__trigger')
    await triggers[0].trigger('click')
    await triggers[1].trigger('click')
    expect(wrapper.findAll('.accordion__panel')).toHaveLength(1)
    expect(wrapper.find('.accordion__panel').text()).toBe('وصف الاستراتيجية')
  })

  it('keeps multiple panels open when multiple is set', async () => {
    const wrapper = mount(Accordion, { props: { items, multiple: true } })
    const triggers = wrapper.findAll('.accordion__trigger')
    await triggers[0].trigger('click')
    await triggers[1].trigger('click')
    expect(wrapper.findAll('.accordion__panel')).toHaveLength(2)
  })
})
