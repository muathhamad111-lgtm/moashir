import { describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useForm } from 'vee-validate'
import ReviewScoreControl from '@/components/review/ReviewScoreControl.vue'
import { createTestGlobals } from '@/test-utils'

const NAME = 'point-31'

function mountControl({
  value = '',
  type = 'score',
  saved = null,
  disabled = false,
  locale = 'en',
} = {}) {
  let form
  const Host = defineComponent({
    setup() {
      form = useForm({ initialValues: { [NAME]: value } })
      return () =>
        h(ReviewScoreControl, {
          name: NAME,
          label: 'Approved plan',
          scoringType: type,
          saved,
          disabled,
        })
    },
  })
  const wrapper = mount(Host, { global: { plugins: createTestGlobals({ locale }).plugins } })
  return { wrapper, value: () => form.values[NAME] }
}

const steps = (wrapper) => wrapper.findAll('.review-score__step')

describe('ReviewScoreControl', () => {
  it('shows the value on its scale, labelled for screen readers by the item', () => {
    const { wrapper } = mountControl({ value: '7' })
    expect(wrapper.find('.review-score__label').text()).toBe('Approved plan')
    expect(wrapper.find('input').element.value).toBe('7')
    expect(wrapper.find('.review-score__max').text()).toBe('/ 10')
  })

  it('steps a score by 1 and a relative value by 5, within the scale', async () => {
    const score = mountControl({ value: '9.5' })
    await steps(score.wrapper)[1].trigger('click')
    expect(score.value()).toBe('10')
    expect(steps(score.wrapper)[1].attributes('disabled')).toBeDefined()

    const relative = mountControl({ value: '80', type: 'relative' })
    expect(relative.wrapper.find('.review-score__max').text()).toBe('%')
    await steps(relative.wrapper)[1].trigger('click')
    expect(relative.value()).toBe('85')
    await steps(relative.wrapper)[0].trigger('click')
    await steps(relative.wrapper)[0].trigger('click')
    expect(relative.value()).toBe('75')
  })

  it("shows the server's percent once saved and never converts an unsaved value", async () => {
    const { wrapper } = mountControl({
      value: '8.50',
      saved: { rawValue: '8.50', percent: '85.00' },
    })
    await flushPromises()
    expect(wrapper.find('.review-score__percent').text()).toBe('85.00%')
    await wrapper.find('input').setValue('9')
    await flushPromises()
    expect(wrapper.find('.review-score__percent').exists()).toBe(false)
    expect(wrapper.find('.review-score__unsaved').text()).toBe('Not saved')
    expect(wrapper.text()).not.toContain('90')
  })

  it('keeps digits only and moves with the arrow keys', async () => {
    const { wrapper, value } = mountControl()
    await wrapper.find('input').setValue('٧٫٥x')
    expect(value()).toBe('7.5')
    await wrapper.find('input').trigger('keydown', { key: 'ArrowUp' })
    expect(value()).toBe('8.5')
  })

  it('locks the control before the review starts', () => {
    const { wrapper } = mountControl({ value: '5', disabled: true })
    expect(wrapper.find('input').attributes('disabled')).toBeDefined()
    expect(steps(wrapper).every((button) => button.attributes('disabled') !== undefined)).toBe(true)
  })

  describe('achieved / not achieved', () => {
    const options = (wrapper) => wrapper.findAll('[role="radio"]')

    it('offers two large buttons that behave as one choice', async () => {
      const { wrapper, value } = mountControl({ type: 'achievement' })
      expect(wrapper.find('[role="radiogroup"]').attributes('aria-label')).toBe('Approved plan')
      expect(options(wrapper).map((button) => button.text())).toEqual(['Achieved', 'Not achieved'])
      await options(wrapper)[0].trigger('click')
      expect(value()).toBe('1')
      expect(options(wrapper)[0].attributes('aria-checked')).toBe('true')
      await options(wrapper)[0].trigger('keydown', { key: 'ArrowLeft' })
      expect(value()).toBe('0')
      expect(options(wrapper)[1].attributes('aria-checked')).toBe('true')
    })

    it('marks a choice that differs from the saved one as not saved', async () => {
      const { wrapper } = mountControl({
        type: 'achievement',
        value: '1.00',
        saved: { rawValue: '1.00', percent: '100.00' },
      })
      expect(wrapper.find('.review-score__unsaved').exists()).toBe(false)
      await options(wrapper)[1].trigger('click')
      expect(wrapper.find('.review-score__unsaved').exists()).toBe(true)
    })

    it('renders the Arabic labels', () => {
      const { wrapper } = mountControl({ type: 'achievement', locale: 'ar' })
      expect(options(wrapper)[0].text()).toBe('متحقق')
    })
  })
})
