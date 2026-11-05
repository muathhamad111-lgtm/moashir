import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ProfileCompletenessCard from '@/components/entities/profile/portal/ProfileCompletenessCard.vue'
import { createTestGlobals } from '@/test-utils'

describe('ProfileCompletenessCard', () => {
  it('shows the server percent and marks required, missing and optional items', () => {
    const wrapper = mount(ProfileCompletenessCard, {
      props: {
        completion: {
          percent: 57,
          items: [
            { key: 'basic_data', isComplete: true, isRequired: true },
            { key: 'license_number', isComplete: false, isRequired: true },
            { key: 'website_url', isComplete: false, isRequired: false },
          ],
        },
      },
      global: createTestGlobals(),
    })
    expect(wrapper.find('.profile-completeness__value').text()).toBe('57%')
    expect(wrapper.find('.profile-completeness__ring').attributes('aria-label')).toBe('57%')
    const states = wrapper.findAll('.profile-completeness__state').map((item) => item.text())
    expect(states).toEqual(['مكتمل', 'ناقص', 'اختياري'])
    expect(wrapper.findAll('.profile-completeness__label')[1].text()).toBe('رقم الترخيص')
  })
})
