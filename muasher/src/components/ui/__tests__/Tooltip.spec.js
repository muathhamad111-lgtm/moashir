import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import Tooltip from '@/components/ui/Tooltip.vue'

function factory() {
  return mount(Tooltip, {
    props: { text: 'يجب أن يكون مجموع الأوزان 100٪' },
    slots: { default: '<button type="button">؟</button>' },
  })
}

describe('Tooltip', () => {
  it('is hidden until hover and shows a role=tooltip bubble', async () => {
    const wrapper = factory()
    expect(wrapper.find('[role="tooltip"]').exists()).toBe(false)
    await wrapper.find('.tooltip').trigger('mouseenter')
    expect(wrapper.find('[role="tooltip"]').text()).toBe('يجب أن يكون مجموع الأوزان 100٪')
    await wrapper.find('.tooltip').trigger('mouseleave')
    expect(wrapper.find('[role="tooltip"]').exists()).toBe(false)
  })

  it('shows on keyboard focus and hides on Escape', async () => {
    const wrapper = factory()
    await wrapper.find('.tooltip').trigger('focusin')
    expect(wrapper.find('[role="tooltip"]').exists()).toBe(true)
    await wrapper.find('.tooltip').trigger('keydown', { key: 'Escape' })
    expect(wrapper.find('[role="tooltip"]').exists()).toBe(false)
  })
})
