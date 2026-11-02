import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WizardStepper from '@/components/wizard/WizardStepper.vue'
import { createTestGlobals } from '@/test-utils'

const steps = [
  { id: 'data', label: 'بيانات الجهة' },
  { id: 'evidence', label: 'السيرة والشواهد', valid: false },
  { id: 'review', label: 'المراجعة' },
]

function factory(props = {}) {
  return mount(WizardStepper, {
    props: { steps, currentIndex: 1, ...props },
    global: createTestGlobals(),
  })
}

describe('WizardStepper', () => {
  it('marks complete, active, and invalid steps with aria-current on the active one', () => {
    const wrapper = factory()
    const stepEls = wrapper.findAll('.wizard__step')
    expect(stepEls[0].classes()).toContain('is-complete')
    expect(stepEls[1].classes()).toContain('is-active')
    expect(stepEls[1].classes()).toContain('is-invalid')
    expect(stepEls[1].find('[aria-current="step"]').exists()).toBe(true)
  })

  it('emits select with the step index when clickable', async () => {
    const wrapper = factory()
    await wrapper.findAll('.wizard__button')[2].trigger('click')
    expect(wrapper.emitted('select')[0]).toEqual([2])
  })

  it('disables navigation when not clickable', async () => {
    const wrapper = factory({ clickable: false })
    const button = wrapper.findAll('.wizard__button')[2]
    expect(button.attributes('disabled')).toBeDefined()
    await button.trigger('click')
    expect(wrapper.emitted('select')).toBeUndefined()
  })
})
