import { describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useForm } from 'vee-validate'
import RawScoreField from '@/components/assessment/RawScoreField.vue'
import { createTestGlobals } from '@/test-utils'

function mountField({ value = '', saved = null, disabled = false } = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  const Host = defineComponent({
    setup() {
      useForm({ initialValues: { 'point-1001': value } })
      return () =>
        h(RawScoreField, {
          name: 'point-1001',
          label: 'Score of M1.1.1',
          scoringType: 'score',
          saved,
          disabled,
        })
    },
  })
  return mount(Host, { global: { plugins: globals.plugins } })
}

describe('RawScoreField', () => {
  it('shows the saved raw value and the percent exactly as returned by the server', async () => {
    const wrapper = mountField({ value: '7.50', saved: { rawValue: '7.50', percent: '75.00' } })
    await flushPromises()
    expect(wrapper.find('label').text()).toBe('Score of M1.1.1')
    expect(wrapper.find('.field__hint').text()).toBe('Saved 7.5 of 10, reflected as 75.00%')
    expect(wrapper.find('.raw-score--saved').exists()).toBe(true)
  })

  it('never computes a percent for an unsaved value', async () => {
    const wrapper = mountField({ value: '7.50', saved: { rawValue: '7.50', percent: '75.00' } })
    await wrapper.find('input').setValue('9')
    await flushPromises()
    expect(wrapper.find('.field__hint').text()).toBe('Not saved yet, out of 10')
    expect(wrapper.text()).not.toContain('90')
  })

  it('treats 7.5 and 7.50 as the same saved value and guides an empty field', async () => {
    const same = mountField({ value: '7.5', saved: { rawValue: '7.50', percent: '75.00' } })
    await flushPromises()
    expect(same.find('.field__hint').text()).toContain('Saved')
    const empty = mountField()
    await flushPromises()
    expect(empty.find('.field__hint').text()).toBe('Enter a score from 0 to 10')
  })

  it('is disabled when read-only and keeps the decimal keyboard', async () => {
    const wrapper = mountField({ disabled: true })
    await flushPromises()
    const input = wrapper.find('input')
    expect(input.attributes('disabled')).toBeDefined()
    expect(input.attributes('inputmode')).toBe('decimal')
  })
})

describe('RawScoreField stepper', () => {
  function buttons(wrapper) {
    const [decrease, increase] = wrapper.findAll('.score-stepper__button')
    return { decrease, increase }
  }

  it('offers labelled decrease and increase buttons inside the field', async () => {
    const wrapper = mountField({ value: '7.5' })
    await flushPromises()
    const { decrease, increase } = buttons(wrapper)
    const input = wrapper.find('input')
    expect(decrease.attributes('aria-label')).toBe('Decrease Score of M1.1.1')
    expect(increase.attributes('aria-label')).toBe('Increase Score of M1.1.1')
    expect(increase.attributes('aria-controls')).toBe(input.attributes('id'))
    expect(wrapper.find('.field__frame--addon .score-stepper').exists()).toBe(true)
    await increase.trigger('click')
    await flushPromises()
    expect(input.element.value).toBe('8.5')
    await decrease.trigger('click')
    await decrease.trigger('click')
    await flushPromises()
    expect(input.element.value).toBe('6.5')
    expect(wrapper.find('.field__hint').text()).toBe('Not saved yet, out of 10')
  })

  it('starts an empty field from zero and stops at the bounds', async () => {
    const wrapper = mountField()
    await flushPromises()
    const { decrease, increase } = buttons(wrapper)
    expect(decrease.attributes('disabled')).toBeDefined()
    await increase.trigger('click')
    await flushPromises()
    expect(wrapper.find('input').element.value).toBe('1')
    await wrapper.find('input').setValue('10')
    await flushPromises()
    expect(increase.attributes('disabled')).toBeDefined()
    expect(decrease.attributes('disabled')).toBeUndefined()
  })

  it('steps with the up and down arrow keys and still accepts typed values', async () => {
    const wrapper = mountField({ value: '3' })
    await flushPromises()
    const input = wrapper.find('input')
    await input.trigger('keydown', { key: 'ArrowUp' })
    await flushPromises()
    expect(input.element.value).toBe('4')
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'ArrowDown' })
    await flushPromises()
    expect(input.element.value).toBe('2')
    await input.setValue('7.25')
    await flushPromises()
    expect(input.element.value).toBe('7.25')
  })

  it('disables both buttons and the arrow keys when the field is read-only', async () => {
    const wrapper = mountField({ value: '5', disabled: true })
    await flushPromises()
    const { decrease, increase } = buttons(wrapper)
    expect(decrease.attributes('disabled')).toBeDefined()
    expect(increase.attributes('disabled')).toBeDefined()
    await wrapper.find('input').trigger('keydown', { key: 'ArrowUp' })
    await flushPromises()
    expect(wrapper.find('input').element.value).toBe('5')
  })
})

describe('RawScoreField typing', () => {
  it.each([
    ['10تال', '10'],
    ['٧٫٥', '7.5'],
    ['1,5', '1.5'],
    ['7.555', '7.55'],
    ['abc', ''],
  ])('keeps only the number when %s is typed or pasted', async (typed, kept) => {
    const wrapper = mountField()
    await flushPromises()
    const input = wrapper.find('input')
    await input.setValue(typed)
    await flushPromises()
    expect(input.element.value).toBe(kept)
  })
})
