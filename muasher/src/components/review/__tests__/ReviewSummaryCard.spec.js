import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import ReviewSummaryCard from '@/components/review/ReviewSummaryCard.vue'

const SUMMARY = { body: 'ملخص سابق.', version: 2, updatedAt: '2026-10-27T11:20:00Z' }

function render(summary) {
  return mount(ReviewSummaryCard, {
    props: { summary },
    global: createTestGlobals({ locale: 'ar' }),
  })
}

describe('ReviewSummaryCard', () => {
  it('shows the saved text read only to the Follower and the System Administrator', () => {
    const wrapper = render(SUMMARY)
    expect(wrapper.find('textarea').exists()).toBe(false)
    expect(wrapper.find('.review-summary__title').text()).toBe('ملخص المقيّم')
    expect(wrapper.find('.review-summary__text').text()).toBe('ملخص سابق.')
    expect(wrapper.find('[data-testid="review-summary-version"]').exists()).toBe(true)
  })

  it('says when no summary was written', () => {
    expect(render(null).find('.review-summary__empty').exists()).toBe(true)
  })
})
