import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WidgetCriteria from '@/components/dashboard/widgets/WidgetCriteria.vue'
import { createTestGlobals } from '@/test-utils'

describe('WidgetCriteria', () => {
  it('lists each criterion by name only', () => {
    const wrapper = mount(WidgetCriteria, {
      props: {
        data: [
          {
            code: 'M1',
            name: { ar: 'القيادة', en: 'Leadership' },
            averagePercent: '80.00',
            results: 3,
          },
        ],
      },
      global: createTestGlobals({ locale: 'en' }),
    })
    expect(wrapper.find('.widget-criteria__name').text()).toBe('Leadership')
    expect(wrapper.text()).not.toContain('M1')
  })
})
