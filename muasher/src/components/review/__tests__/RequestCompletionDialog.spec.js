import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import RequestCompletionDialog from '@/components/review/RequestCompletionDialog.vue'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const CRITERIA = {
  mains: [
    {
      id: 1,
      name: { en: 'Leadership' },
      attachments: [],
      subs: [
        {
          id: 11,
          name: { en: 'Direction' },
          hasCv: true,
          evaluationType: 'guiding-points',
          points: [{ id: 111 }, { id: 112 }],
        },
        { id: 12, name: { en: 'Governance' }, hasCv: false, evaluationType: 'direct', points: [] },
      ],
    },
  ],
}

const SCORES = {
  subs: { 12: { rawValue: '9.00', percent: '90.00' } },
  points: {
    111: { rawValue: '0.00', percent: '0.00' },
    112: { rawValue: '1.00', percent: '100.00' },
  },
}

function mountDialog(send = vi.fn().mockResolvedValue({ data: {} })) {
  const wrapper = mount(RequestCompletionDialog, {
    props: {
      modelValue: false,
      assessmentId: 9107,
      criteria: CRITERIA,
      scores: SCORES,
      askReason: () => Promise.resolve(null),
      send,
      bounds: { min: '2026-11-21', max: '2027-01-19' },
    },
    global: { ...createTestGlobals({ locale: 'en' }), stubs: { teleport: true } },
    attachTo: document.body,
  })
  return { wrapper, send }
}

const currentStep = (wrapper) => wrapper.find('.form-steps__step.is-current').text()

describe('RequestCompletionDialog', () => {
  it('suggests the weak items, then asks for the message and deadline, then shows a review', async () => {
    const { wrapper, send } = mountDialog()
    await wrapper.setProps({ modelValue: true })
    await flushPromises()
    expect(wrapper.find('.completion-dialog__suggested').text()).toContain('We suggested 1 item')
    expect(wrapper.findAll('.completion-summary__item')).toHaveLength(1)
    expect(wrapper.find('.completion-summary__label').text()).toBe('Direction — Attachments')
    expect(currentStep(wrapper)).toContain('Items')

    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(currentStep(wrapper)).toContain('Message and deadline'))
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(currentStep(wrapper)).toContain('Message and deadline')

    await wrapper.find('textarea[name="notes"]').setValue('Please attach the approved plan.')
    await wrapper.find('input[type="date"]').setValue('2026-11-28')
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(currentStep(wrapper)).toContain('Review and send'))
    expect(wrapper.find('.completion-review').text()).toContain('Please attach the approved plan.')

    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(send).toHaveBeenCalled())
    expect(send).toHaveBeenCalledWith(
      9107,
      expect.objectContaining({
        notes: 'Please attach the approved plan.',
        items: [{ subCriterionId: 11, kind: 'evidence' }],
        expiryOutcome: 'continue_as_submitted',
      }),
    )
    wrapper.unmount()
  })

  it('brings back the step that holds a field the server rejected', async () => {
    const send = vi.fn().mockRejectedValue({
      normalized: { status: 422, message: 'Invalid', fieldErrors: { notes: ['Too long.'] } },
    })
    const { wrapper } = mountDialog(send)
    await wrapper.setProps({ modelValue: true })
    await flushPromises()
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(currentStep(wrapper)).toContain('Message and deadline'))
    await wrapper.find('textarea[name="notes"]').setValue('Please attach the approved plan.')
    await wrapper.find('input[type="date"]').setValue('2026-11-28')
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(currentStep(wrapper)).toContain('Review and send'))
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(send).toHaveBeenCalled())
    await vi.waitFor(() => expect(currentStep(wrapper)).toContain('Message and deadline'))
    wrapper.unmount()
  })
})
