import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FeedbackCriterion from '@/components/reports/FeedbackCriterion.vue'
import { createTestGlobals } from '@/test-utils'

describe('FeedbackCriterion', () => {
  it('titles the criterion and its sub-criteria by name only', () => {
    const wrapper = mount(FeedbackCriterion, {
      props: {
        criterion: {
          criterionId: 1,
          code: 'M1',
          name: { ar: 'القيادة', en: 'Leadership' },
          percent: '75.00',
          subs: [
            {
              subId: 101,
              code: 'M1.1',
              name: { ar: 'الحوكمة', en: 'Governance' },
              percent: '70.00',
              earned: '7.00',
              weight: '10.00',
              strengths: null,
              improvementPoints: null,
              notes: null,
            },
          ],
        },
      },
      global: createTestGlobals({ locale: 'en' }),
    })
    expect(wrapper.find('.feedback-criterion__title').text()).toBe('Leadership')
    expect(wrapper.find('.feedback-report__sub-title').text()).toBe('Governance')
    expect(wrapper.text()).not.toMatch(/M1/)
  })
})
