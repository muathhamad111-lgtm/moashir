import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FormPanel from '@/components/form/FormPanel.vue'
import { createTestGlobals } from '@/test-utils'

function mountPanel(props = {}) {
  return mount(FormPanel, {
    props: { title: 'Details', ...props },
    slots: { default: '<input name="field" />' },
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('FormPanel', () => {
  it('submits and undoes in place', async () => {
    const wrapper = mountPanel()
    expect(wrapper.find('section').attributes('aria-labelledby')).toBe(
      wrapper.find('h2').attributes('id'),
    )
    await wrapper.find('form').trigger('submit')
    await wrapper.findAll('button')[1].trigger('click')
    expect(wrapper.emitted('submit')).toHaveLength(1)
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
    expect(wrapper.findAll('button')[1].text()).toBe('Undo changes')
  })

  it('locks the fields and hides the actions when read only', () => {
    const wrapper = mountPanel({ readonly: true })
    expect(wrapper.find('fieldset').attributes('disabled')).toBeDefined()
    expect(wrapper.find('footer').exists()).toBe(false)
  })
})
