import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import Badge from '@/components/ui/Badge.vue'

describe('Badge', () => {
  it('renders the slot with the default muted variant', () => {
    const wrapper = mount(Badge, { slots: { default: 'مسودة' } })
    expect(wrapper.text()).toBe('مسودة')
    expect(wrapper.classes()).toContain('badge')
    expect(wrapper.classes()).toContain('badge--muted')
  })

  it('applies the requested variant class', () => {
    const wrapper = mount(Badge, { props: { variant: 'success' }, slots: { default: 'Paid' } })
    expect(wrapper.classes()).toContain('badge--success')
  })
})
