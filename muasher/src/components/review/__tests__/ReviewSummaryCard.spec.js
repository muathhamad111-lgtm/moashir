import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import ReviewSummaryCard from '@/components/review/ReviewSummaryCard.vue'
import { assessorReviewService } from '@/services/assessorReviewService'

vi.mock('@/services/assessorReviewService', () => ({
  assessorReviewService: { saveSummary: vi.fn() },
}))

const SUMMARY = { body: 'ملخص سابق.', version: 2, updatedAt: '2026-10-27T11:20:00Z' }

function render(props = {}) {
  return mount(ReviewSummaryCard, {
    props: {
      assessmentId: 9108,
      summary: null,
      editable: true,
      askReason: () => Promise.resolve(null),
      ...props,
    },
    global: createTestGlobals({ locale: 'ar' }),
  })
}

describe('ReviewSummaryCard', () => {
  beforeEach(() => vi.mocked(assessorReviewService.saveSummary).mockReset())

  it('counts the characters and blocks a summary over 2000 characters', async () => {
    const wrapper = render()
    await wrapper.find('textarea').setValue('أ'.repeat(2001))
    expect(wrapper.find('.review-summary__counter').text()).toBe('2,001 / 2,000')
    expect(wrapper.find('.review-summary__counter').attributes('aria-live')).toBe('polite')
    expect(wrapper.find('[data-testid="review-summary-save"]').attributes('disabled')).toBeDefined()
  })

  it('saves the trimmed text and reports the new version', async () => {
    vi.mocked(assessorReviewService.saveSummary).mockResolvedValue({
      data: { ...SUMMARY, version: 3 },
    })
    const wrapper = render({ summary: SUMMARY })
    await wrapper.find('textarea').setValue('  ملخص جديد.  ')
    await wrapper.find('[data-testid="review-summary-save"]').trigger('click')
    await flushPromises()
    expect(assessorReviewService.saveSummary).toHaveBeenCalledWith(9108, { summary: 'ملخص جديد.' })
    expect(wrapper.emitted('saved')[0][0].version).toBe(3)
  })

  it('explains a 409 when the assessment is no longer under review', async () => {
    vi.mocked(assessorReviewService.saveSummary).mockRejectedValueOnce(
      Object.assign(new Error('conflict'), {
        normalized: { status: 409, code: 'state.transition_illegal', fieldErrors: {} },
      }),
    )
    const wrapper = render()
    await wrapper.find('textarea').setValue('ملخص.')
    await wrapper.vm.$nextTick()
    await wrapper.find('[data-testid="review-summary-save"]').trigger('click')
    await flushPromises()
    await flushPromises()
    expect(assessorReviewService.saveSummary).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('ليس قيد المراجعة')
  })

  it('waits for the Super Admin reason before saving', async () => {
    const wrapper = render({ requiresReason: true, askReason: () => Promise.resolve(null) })
    await wrapper.find('textarea').setValue('ملخص.')
    await wrapper.find('[data-testid="review-summary-save"]').trigger('click')
    await flushPromises()
    expect(assessorReviewService.saveSummary).not.toHaveBeenCalled()
  })

  it('shows the saved text read only to the Follower and the System Administrator', () => {
    const wrapper = render({ editable: false, summary: SUMMARY })
    expect(wrapper.find('textarea').exists()).toBe(false)
    expect(wrapper.find('.review-summary__text').text()).toBe('ملخص سابق.')
    expect(render({ editable: false }).find('.review-summary__empty').exists()).toBe(true)
  })
})
