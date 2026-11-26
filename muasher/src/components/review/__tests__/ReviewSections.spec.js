import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import ReviewSections from '@/components/review/ReviewSections.vue'

function render(locale = 'ar') {
  const globals = createTestGlobals({ locale })
  return mount(ReviewSections, {
    props: { requestId: 9108 },
    slots: {
      assessment: '<p class="slot-assessment">scores</p>',
      entity: '<p class="slot-entity">profile</p>',
    },
    global: {
      plugins: globals.plugins,
      stubs: { ReviewRequestTab: { template: '<p class="slot-request" />' } },
    },
  })
}

describe('ReviewSections', () => {
  it('opens on the assessment tab, then the request and entity details', () => {
    const wrapper = render()
    const tabs = wrapper.findAll('[role="tab"]')
    expect(tabs.map((tab) => tab.text())).toEqual(['التقييم', 'بيانات الطلب', 'بيانات الجهة'])
    expect(tabs[0].attributes('aria-selected')).toBe('true')
    expect(wrapper.find('.slot-assessment').isVisible()).toBe(true)
    expect(wrapper.find('.slot-request').exists()).toBe(false)
    expect(wrapper.find('.slot-entity').exists()).toBe(false)
  })

  it('keeps the assessment mounted while another tab is open', async () => {
    const wrapper = render('en')
    await wrapper.findAll('[role="tab"]')[1].trigger('click')
    expect(wrapper.find('.slot-request').exists()).toBe(true)
    expect(wrapper.find('.slot-assessment').exists()).toBe(true)
    expect(wrapper.find('.slot-assessment').isVisible()).toBe(false)
    await wrapper.findAll('[role="tab"]')[2].trigger('click')
    expect(wrapper.find('.slot-entity').text()).toBe('profile')
    expect(wrapper.findAll('[role="tab"]').map((tab) => tab.text())).toEqual([
      'Assessment',
      'Request details',
      'Entity details',
    ])
  })
})
