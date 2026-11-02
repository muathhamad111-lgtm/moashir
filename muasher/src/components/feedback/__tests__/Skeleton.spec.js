import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import Skeleton from '@/components/feedback/Skeleton.vue'

describe('Skeleton', () => {
  it('renders the text variant by default, hidden from assistive tech', () => {
    const wrapper = mount(Skeleton)
    expect(wrapper.attributes('aria-hidden')).toBe('true')
    expect(wrapper.find('.skeleton--text').exists()).toBe(true)
  })

  it('renders the requested variant and count', () => {
    const wrapper = mount(Skeleton, { props: { variant: 'circle', count: 3 } })
    expect(wrapper.findAll('.skeleton--circle')).toHaveLength(3)
  })
})
