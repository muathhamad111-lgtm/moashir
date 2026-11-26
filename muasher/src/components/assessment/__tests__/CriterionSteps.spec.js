import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CriterionStepButtons from '@/components/assessment/CriterionStepButtons.vue'
import CriterionStepCounter from '@/components/assessment/CriterionStepCounter.vue'
import { createTestGlobals } from '@/test-utils'

function mountWith(component, props, slots = {}) {
  return mount(component, { props, slots, global: { plugins: createTestGlobals().plugins } })
}

describe('CriterionStepButtons', () => {
  it('shows next on the first step, previous and next in the middle, and the last-step action at the end', async () => {
    const slots = {
      default: '<button class="save">save</button>',
      last: '<button class="send">send</button>',
    }
    const first = mountWith(CriterionStepButtons, { isFirst: true, isLast: false }, slots)
    expect(first.find('[data-testid="step-previous"]').exists()).toBe(false)
    expect(first.find('[data-testid="step-next"]').text()).toBe('التالي')
    expect(first.find('.send').exists()).toBe(false)
    await first.find('[data-testid="step-next"]').trigger('click')
    expect(first.emitted('next')).toHaveLength(1)

    const middle = mountWith(CriterionStepButtons, { isFirst: false, isLast: false }, slots)
    expect(middle.find('[data-testid="step-previous"]').text()).toBe('السابق')
    await middle.find('[data-testid="step-previous"]').trigger('click')
    expect(middle.emitted('previous')).toHaveLength(1)

    const last = mountWith(CriterionStepButtons, { isFirst: false, isLast: true }, slots)
    expect(last.find('[data-testid="step-next"]').exists()).toBe(false)
    expect(last.find('.send').exists()).toBe(true)
    expect(last.find('.save').exists()).toBe(true)
  })

  it('disables the navigation while an action runs', () => {
    const wrapper = mountWith(CriterionStepButtons, {
      isFirst: false,
      isLast: false,
      disabled: true,
    })
    expect(wrapper.find('[data-testid="step-previous"]').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-testid="step-next"]').attributes('disabled')).toBeDefined()
  })
})

describe('CriterionStepCounter', () => {
  it('names the position of the criterion', () => {
    expect(mountWith(CriterionStepCounter, { step: 2, total: 9 }).text()).toBe('المعيار 2 من 9')
  })
})
