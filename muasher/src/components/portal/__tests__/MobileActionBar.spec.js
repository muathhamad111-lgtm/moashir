import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MobileActionBar from '@/components/portal/MobileActionBar.vue'

describe('MobileActionBar', () => {
  it('renders a labelled region around the task actions', () => {
    const wrapper = mount(MobileActionBar, {
      props: { label: 'إجراءات النموذج' },
      slots: { default: '<button type="button">حفظ</button>' },
    })
    expect(wrapper.attributes('role')).toBe('region')
    expect(wrapper.attributes('aria-label')).toBe('إجراءات النموذج')
    expect(wrapper.find('.mobile-action-bar__inner button').text()).toBe('حفظ')
  })
})
