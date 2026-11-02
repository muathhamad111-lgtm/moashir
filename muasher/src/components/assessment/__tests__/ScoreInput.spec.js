import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ScoreInput from '@/components/assessment/ScoreInput.vue'
import { createTestGlobals } from '@/test-utils'

function mountInput(props = {}) {
  return mount(ScoreInput, {
    props: { label: 'Score', modelValue: 0, scaleKind: 'of-10', ...props },
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('ScoreInput', () => {
  it('renders an accessible label bound to the number control', () => {
    const wrapper = mountInput({ label: 'Governance score' })
    const label = wrapper.find('.score-input__label')
    const input = wrapper.find('input')
    expect(label.text()).toBe('Governance score')
    expect(label.attributes('for')).toBe(input.attributes('id'))
  })

  it('shows the of-10 raw value derived from the ratio', () => {
    const wrapper = mountInput({ modelValue: 0.7, scaleKind: 'of-10' })
    expect(wrapper.find('input').element.value).toBe('7')
    expect(wrapper.find('.score-input__percent').text()).toContain('70')
  })

  it('shows the relative (percentage) raw value derived from the ratio', () => {
    const wrapper = mountInput({ modelValue: 0.45, scaleKind: 'relative' })
    expect(wrapper.find('input').element.value).toBe('45')
    expect(wrapper.find('.score-input__percent').text()).toContain('45')
  })

  it('emits a 0..1 ratio when the of-10 value changes', async () => {
    const wrapper = mountInput({ modelValue: 0, scaleKind: 'of-10' })
    const input = wrapper.find('input')
    input.element.value = '8'
    await input.trigger('input')
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([0.8])
  })

  it('emits a 0..1 ratio when the relative value changes', async () => {
    const wrapper = mountInput({ modelValue: 0, scaleKind: 'relative' })
    const input = wrapper.find('input')
    input.element.value = '60'
    await input.trigger('input')
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([0.6])
  })

  it('bounds an over-max entry to a ratio of 1', async () => {
    const wrapper = mountInput({ modelValue: 0, scaleKind: 'of-10' })
    const input = wrapper.find('input')
    input.element.value = '99'
    await input.trigger('input')
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([1])
  })

  it('increments by the scale step via the plus button', async () => {
    const wrapper = mountInput({ modelValue: 0.5, scaleKind: 'of-10' })
    await wrapper.findAll('.score-input__step').at(1).trigger('click')
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([0.6])
  })

  it('decrements by the scale step via the minus button', async () => {
    const wrapper = mountInput({ modelValue: 0.5, scaleKind: 'relative' })
    await wrapper.findAll('.score-input__step').at(0).trigger('click')
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([0.45])
  })

  it('disables the minus button at zero and the plus button at max', () => {
    const min = mountInput({ modelValue: 0 })
    expect(min.findAll('.score-input__step').at(0).attributes('disabled')).toBeDefined()
    const max = mountInput({ modelValue: 1 })
    expect(max.findAll('.score-input__step').at(1).attributes('disabled')).toBeDefined()
  })

  it('gives the stepper buttons accessible names', () => {
    const wrapper = mountInput()
    const [minus, plus] = wrapper.findAll('.score-input__step')
    expect(minus.attributes('aria-label')).toBeTruthy()
    expect(plus.attributes('aria-label')).toBeTruthy()
  })

  it('does not emit while disabled', async () => {
    const wrapper = mountInput({ disabled: true, modelValue: 0.5 })
    await wrapper.findAll('.score-input__step').at(1).trigger('click')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })
})
