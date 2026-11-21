import { describe, expect, it, vi } from 'vitest'
import { ref, shallowRef } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import ReviewRoundRows from '@/components/review/ReviewRoundRows.vue'
import ReviewOverrideBand from '@/components/review/ReviewOverrideBand.vue'
import ScoreOverrideReview from '@/components/review/ScoreOverrideReview.vue'
import ReviewCompletionAction from '@/components/review/ReviewCompletionAction.vue'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const globals = (locale = 'en') => ({
  global: { ...createTestGlobals({ locale }), stubs: { teleport: true } },
})

const BEFORE = { round: 1, rawValue: '3.00', percent: '30.00', scoredAt: '2026-10-26T08:00:00Z' }

describe('ReviewRoundRows', () => {
  it('shows each round with its result and waits for the current one', async () => {
    const wrapper = mount(ReviewRoundRows, {
      props: {
        rows: [
          { round: 1, entry: BEFORE, note: 'Attach the plan.', pending: false },
          { round: 2, entry: null, note: null, pending: true },
        ],
        scoringType: 'score',
        label: 'Published vision',
        canConfirm: true,
      },
      ...globals(),
    })
    const rows = wrapper.findAll('tbody tr')
    expect(rows[0].text()).toContain('Round 1 (before completion)')
    expect(rows[0].text()).toContain('3 of 10')
    expect(rows[0].text()).toContain('Attach the plan.')
    expect(rows[1].classes()).toContain('is-pending')
    expect(rows[1].text()).toContain('Waiting for a new result')
    await rows[1].find('button').trigger('click')
    expect(wrapper.emitted('confirm')).toHaveLength(1)
  })

  it('reads an achievement result as achieved or not', () => {
    const wrapper = mount(ReviewRoundRows, {
      props: {
        rows: [{ round: 1, entry: { ...BEFORE, percent: '0.00' }, note: null, pending: false }],
        scoringType: 'achievement',
        label: 'Plan',
      },
      ...globals('ar'),
    })
    expect(wrapper.find('tbody tr').text()).toContain('غير متحقق')
    expect(wrapper.find('button').exists()).toBe(false)
  })
})

function fakeOverride({ changed = 1, valid = true, preview = null, submit = vi.fn() } = {}) {
  return {
    active: ref(true),
    preview: shallowRef(preview),
    previewing: ref(false),
    changedUnits: ref(
      Array.from({ length: changed }, (_, index) => ({
        level: 'point',
        id: 31 + index,
        code: `M1.1.${index + 1}`,
        label: { en: `Item ${index + 1}` },
      })),
    ),
    isValid: ref(valid),
    saved: () => ({ rawValue: '8.00', percent: '80.00' }),
    leave: vi.fn(),
    submit,
  }
}

const PREVIEW = {
  before: { percent: '57.00', level: 'silver' },
  after: { percent: '58.40', level: 'silver' },
  changes: [],
}

describe('ReviewOverrideBand', () => {
  it('sums up the changes with the total and level the server computed', async () => {
    const override = fakeOverride({ preview: PREVIEW })
    const wrapper = mount(ReviewOverrideBand, { props: { override }, ...globals() })
    expect(wrapper.find('.override-band__summary').text()).toContain(
      '1 item changed · Total 57.00% → 58.40% · Silver level (unchanged)',
    )
    await wrapper.find('[data-testid="override-review"]').trigger('click')
    expect(wrapper.emitted('review')).toHaveLength(1)
  })

  it('cannot be reviewed without a change or with an invalid value', () => {
    const none = mount(ReviewOverrideBand, {
      props: { override: fakeOverride({ changed: 0 }) },
      ...globals(),
    })
    expect(none.find('[data-testid="override-review"]').attributes('disabled')).toBeDefined()
    const invalid = mount(ReviewOverrideBand, {
      props: { override: fakeOverride({ valid: false }) },
      ...globals(),
    })
    expect(invalid.find('[data-testid="override-review"]').attributes('disabled')).toBeDefined()
    expect(invalid.text()).toContain('Fix the invalid values')
  })
})

describe('ScoreOverrideReview', () => {
  function mountReview(override) {
    return mount(ScoreOverrideReview, {
      props: { modelValue: true, override, values: { 'point-31': '9.5' } },
      ...globals(),
      attachTo: document.body,
    })
  }

  it('lists only the changed items and needs a reason of 20 characters', async () => {
    const submit = vi.fn().mockResolvedValue()
    const wrapper = mountReview(fakeOverride({ preview: PREVIEW, submit }))
    const row = wrapper.find('tbody tr')
    expect(row.text()).toContain('M1.1.1')
    expect(row.text()).toContain('8')
    expect(row.text()).toContain('9.5')
    expect(row.text()).toContain('+1.5')
    await wrapper.find('textarea').setValue('too short')
    await wrapper.find('form').trigger('submit')
    expect(submit).not.toHaveBeenCalled()
    expect(wrapper.find('.field__error').exists()).toBe(true)
    await wrapper.find('textarea').setValue('Corrected after checking the attached evidence.')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(submit).toHaveBeenCalledWith('Corrected after checking the attached evidence.')
    expect(wrapper.emitted('update:modelValue')).toEqual([[false]])
    wrapper.unmount()
  })
})

describe('ReviewCompletionAction', () => {
  it('asks for every item to be scored before a completion request', async () => {
    const waiting = mount(ReviewCompletionAction, { props: { missing: 16 }, ...globals() })
    const button = waiting.find('[data-testid="review-request-completion"]')
    expect(button.text()).toBe('Score every item first (16 left)')
    expect(button.attributes('disabled')).toBeDefined()
    const ready = mount(ReviewCompletionAction, { props: { missing: 0 }, ...globals() })
    await ready.find('[data-testid="review-request-completion"]').trigger('click')
    expect(ready.emitted('open')).toHaveLength(1)
  })
})
