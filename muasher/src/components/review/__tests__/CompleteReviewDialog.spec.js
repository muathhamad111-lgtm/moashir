import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import CompleteReviewDialog from '@/components/review/CompleteReviewDialog.vue'
import { assessorReviewService } from '@/services/assessorReviewService'

vi.mock('@/services/assessorReviewService', () => ({
  assessorReviewService: { saveSummary: vi.fn() },
}))

const SUMMARY = { body: 'ملخص سابق.', version: 2, updatedAt: '2026-10-27T11:20:00Z' }

function render(props = {}) {
  return mount(CompleteReviewDialog, {
    props: {
      modelValue: true,
      message: 'أكمل التقييم؟',
      assessmentId: 9108,
      offersSummary: true,
      summary: null,
      ...props,
    },
    global: createTestGlobals({ locale: 'ar' }),
    attachTo: document.body,
  })
}

const textarea = () => document.querySelector('textarea[name="assessor-summary"]')
const confirmButton = () => document.querySelector('.modal__footer .btn')

async function type(value) {
  textarea().value = value
  textarea().dispatchEvent(new Event('input'))
  await flushPromises()
}

describe('CompleteReviewDialog with the assessor summary', () => {
  beforeEach(() => vi.mocked(assessorReviewService.saveSummary).mockReset())

  it('saves a changed summary first, then completes', async () => {
    vi.mocked(assessorReviewService.saveSummary).mockResolvedValue({
      data: { ...SUMMARY, version: 3 },
    })
    const wrapper = render({ summary: SUMMARY })
    await flushPromises()
    expect(textarea().value).toBe('ملخص سابق.')
    await type('  ملخص جديد.  ')
    confirmButton().click()
    await flushPromises()
    expect(assessorReviewService.saveSummary).toHaveBeenCalledWith(9108, { summary: 'ملخص جديد.' })
    expect(wrapper.emitted('saved')[0][0].version).toBe(3)
    expect(wrapper.emitted('confirm')).toHaveLength(1)
    wrapper.unmount()
  })

  it('completes without a save when the summary did not change', async () => {
    const wrapper = render({ summary: SUMMARY })
    await flushPromises()
    confirmButton().click()
    await flushPromises()
    expect(assessorReviewService.saveSummary).not.toHaveBeenCalled()
    expect(wrapper.emitted('confirm')).toHaveLength(1)
    wrapper.unmount()
  })

  it('stays open and explains a failed save instead of completing', async () => {
    vi.mocked(assessorReviewService.saveSummary).mockRejectedValueOnce(
      Object.assign(new Error('conflict'), {
        normalized: { status: 409, code: 'state.transition_illegal', fieldErrors: {} },
      }),
    )
    const wrapper = render()
    await flushPromises()
    await type('ملخص.')
    confirmButton().click()
    await flushPromises()
    expect(wrapper.emitted('confirm')).toBeUndefined()
    expect(document.body.textContent).toContain('ليس قيد المراجعة')
    wrapper.unmount()
  })

  it('counts the characters and refuses a summary over 2000 characters', async () => {
    const wrapper = render()
    await flushPromises()
    await type('أ'.repeat(2001))
    expect(document.querySelector('.review-summary__counter').textContent.trim()).toBe(
      '2,001 / 2,000',
    )
    confirmButton().click()
    await flushPromises()
    expect(assessorReviewService.saveSummary).not.toHaveBeenCalled()
    expect(wrapper.emitted('confirm')).toBeUndefined()
    wrapper.unmount()
  })

  it('shows no summary field outside the accredited path', async () => {
    const wrapper = render({ offersSummary: false })
    await flushPromises()
    expect(textarea()).toBeNull()
    wrapper.unmount()
  })
})
