import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { createTestGlobals } from '@/test-utils'

describe('StatusBadge', () => {
  it('maps a domain status to variant and Arabic label', () => {
    const wrapper = mount(StatusBadge, {
      props: { status: 'under-review' },
      global: createTestGlobals(),
    })
    expect(wrapper.text()).toBe('قيد المراجعة')
    expect(wrapper.find('.badge--info').exists()).toBe(true)
  })

  it('renders the English label when locale is en', () => {
    const wrapper = mount(StatusBadge, {
      props: { status: 'awaiting-secretary-approval' },
      global: createTestGlobals({ locale: 'en' }),
    })
    expect(wrapper.text()).toBe('Awaiting secretary approval')
    expect(wrapper.find('.badge--warning').exists()).toBe(true)
  })

  it('always shows a text label so color is never the only signal', () => {
    const wrapper = mount(StatusBadge, {
      props: { status: 'failed' },
      global: createTestGlobals(),
    })
    expect(wrapper.text().length).toBeGreaterThan(0)
  })
})
