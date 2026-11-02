import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WeightDistributionEditor from '@/components/criteria/WeightDistributionEditor.vue'
import { createTestGlobals } from '@/test-utils'

function makeChildren() {
  return [
    { id: 1, label: 'M1.1 Strategy', weight: '400.00', active: true },
    { id: 2, label: 'M1.2 Governance', weight: '600.00', active: true },
  ]
}

function mountEditor(props = {}) {
  return mount(WeightDistributionEditor, {
    props: { parentWeight: '1000.00', children: makeChildren(), mode: 'manual', ...props },
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('WeightDistributionEditor', () => {
  it('reports a balanced distribution with two-decimal totals', () => {
    const wrapper = mountEditor()
    expect(wrapper.find('.weight-editor__status--success').exists()).toBe(true)
    expect(wrapper.find('.weight-editor__totals').text()).toContain('1,000.00')
  })

  it('flags over-allocation by the exact difference without float drift', () => {
    const wrapper = mountEditor({
      children: [
        { id: 1, label: 'A', weight: '333.34', active: true },
        { id: 2, label: 'B', weight: '666.67', active: true },
      ],
    })
    expect(wrapper.find('.weight-editor__status--danger').text()).toContain('0.01')
  })

  it('does not count inactive children and locks their input', () => {
    const wrapper = mountEditor({
      children: [
        { id: 1, label: 'A', weight: '400.00', active: true },
        { id: 2, label: 'B', weight: '600.00', active: false },
      ],
    })
    expect(wrapper.find('.weight-editor__status--warning').text()).toContain('600.00')
    expect(wrapper.findAll('.weight-editor__input')[1].attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Inactive, not counted')
  })

  it('asks the parent to switch the mode instead of computing shares locally', async () => {
    const wrapper = mountEditor()
    await wrapper.findAll('.weight-editor__mode')[0].trigger('click')
    expect(wrapper.emitted('update:mode')[0]).toEqual(['equal'])
    expect(wrapper.emitted('update:children')).toBeUndefined()
  })

  it('emits the edited weight as typed in manual mode', async () => {
    const wrapper = mountEditor()
    await wrapper.findAll('.weight-editor__input')[0].setValue('450.50')
    const emitted = wrapper.emitted('update:children').at(-1)[0]
    expect(emitted.find((child) => child.id === 1).weight).toBe('450.50')
  })

  it('disables inputs in equal mode', () => {
    const wrapper = mountEditor({ mode: 'equal' })
    expect(
      wrapper
        .findAll('.weight-editor__input')
        .every((input) => input.attributes('disabled') !== undefined),
    ).toBe(true)
  })
})
