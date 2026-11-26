import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ReviewCriteriaStrip from '@/components/review/ReviewCriteriaStrip.vue'
import ReviewActions from '@/components/review/ReviewActions.vue'
import ReviewFoldedText from '@/components/review/ReviewFoldedText.vue'
import ReviewFeedbackFold from '@/components/review/ReviewFeedbackFold.vue'
import ReviewEvidenceRows from '@/components/review/ReviewEvidenceRows.vue'
import { createTestGlobals } from '@/test-utils'

const globals = (locale = 'en') => ({ global: createTestGlobals({ locale }) })

describe('ReviewCriteriaStrip', () => {
  const STATIONS = [
    { id: 1, name: { en: 'Leadership' }, scored: 6, scorable: 6, state: 'complete' },
    { id: 2, name: { en: 'Planning' }, scored: 1, scorable: 3, state: 'partial' },
    { id: 3, name: { en: 'Results' }, scored: 0, scorable: 2, state: 'empty' },
  ]

  it('names every criterion with its count and state, and outlines the open one', () => {
    const wrapper = mount(ReviewCriteriaStrip, {
      props: { stations: STATIONS, activeId: 2 },
      ...globals(),
    })
    const steps = wrapper.findAll('.review-strip__step')
    expect(steps.map((step) => step.find('.review-strip__name').text())).toEqual([
      'Leadership',
      'Planning',
      'Results',
    ])
    expect(steps[0].classes()).toContain('is-complete')
    expect(steps[0].find('.review-strip__meta').text()).toBe('6 / 6 · Complete')
    expect(steps[1].attributes('aria-current')).toBe('step')
    expect(steps[1].find('.review-strip__meta').text()).toBe('1 / 3 · Partly scored')
    expect(steps[2].find('.review-strip__marker').text()).toBe('3')
  })

  it('opens the criterion that is clicked', async () => {
    const wrapper = mount(ReviewCriteriaStrip, {
      props: { stations: STATIONS, activeId: 1 },
      ...globals(),
    })
    await wrapper.findAll('.review-strip__step')[2].trigger('click')
    expect(wrapper.emitted('select')[0]).toEqual([3])
  })
})

describe('ReviewActions', () => {
  const mountBar = (props = {}) =>
    mount(ReviewActions, {
      props: {
        canScore: true,
        isFirst: false,
        isLast: false,
        nextName: 'Strategic Planning',
        missing: 4,
        ...props,
      },
      ...globals(),
    })
  const testIds = (wrapper) =>
    wrapper.findAll('[data-testid]').map((element) => element.attributes('data-testid'))

  it('puts save first and the way between criteria last, and counts what is left instead of completing', () => {
    const wrapper = mountBar({ dirty: true })
    // Operator 2026-11-22: «التالي» sits at the inline end (left in RTL); «إكمال التقييم» waits for every item.
    expect(testIds(wrapper)).toEqual([
      'review-save',
      'review-remaining',
      'step-previous',
      'step-next',
    ])
    expect(wrapper.find('[data-testid="step-next"]').text()).toBe('Next: Strategic Planning')
    expect(wrapper.find('[data-testid="review-remaining"]').text()).toBe('Items left: 4')
    expect(wrapper.find('[data-testid="review-complete"]').exists()).toBe(false)
    // The save state is for screen readers only (operator, assessor journey #30).
    expect(wrapper.find('.review-actions__status').attributes('aria-live')).toBe('polite')
    expect(wrapper.find('.review-actions__status').text()).toContain('unsaved')
  })

  it('saves only when something changed and reports the events', async () => {
    const wrapper = mountBar()
    expect(wrapper.find('[data-testid="review-save"]').attributes('disabled')).toBeDefined()
    await wrapper.find('[data-testid="step-next"]').trigger('click')
    expect(wrapper.emitted('next')).toHaveLength(1)
  })

  it('keeps only the way between criteria for a reader', () => {
    const wrapper = mountBar({ canScore: false, isFirst: true })
    expect(testIds(wrapper)).toEqual(['step-next'])
  })

  it('names the last step without a next criterion', () => {
    const wrapper = mountBar({ isLast: true, missing: 0 })
    expect(wrapper.find('[data-testid="step-next"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="review-complete"]').text()).toBe('Complete the assessment')
  })
})

describe('ReviewFoldedText', () => {
  it('shows two lines of a long text first and the rest on request', async () => {
    const wrapper = mount(ReviewFoldedText, {
      props: { title: 'CV entry', text: 'x'.repeat(400), emptyText: 'No CV.' },
      ...globals(),
    })
    expect(wrapper.find('.review-folded__text').classes()).toContain('is-folded')
    const toggle = wrapper.find('.review-folded__toggle')
    expect(toggle.attributes('aria-expanded')).toBe('false')
    await toggle.trigger('click')
    expect(wrapper.find('.review-folded__text').classes()).not.toContain('is-folded')
    expect(toggle.text()).toBe('Show less')
  })

  it('shows a short text whole and says when there is none', () => {
    const short = mount(ReviewFoldedText, {
      props: { title: 'CV entry', text: 'Short.', emptyText: 'No CV.' },
      ...globals(),
    })
    expect(short.find('.review-folded__toggle').exists()).toBe(false)
    const empty = mount(ReviewFoldedText, {
      props: { title: 'CV entry', text: '', emptyText: 'No CV.' },
      ...globals(),
    })
    expect(empty.find('.review-folded__empty').text()).toBe('No CV.')
  })
})

describe('ReviewFeedbackFold', () => {
  const mountFold = (fields, disabled = true) =>
    mount(ReviewFeedbackFold, { props: { subId: 21, fields, disabled }, ...globals() })

  it('stays folded while read-only and counts the written fields', () => {
    const wrapper = mountFold({ strengths: 'Clear plan', improvements: '', notes: '' })
    expect(wrapper.find('details').attributes('open')).toBeUndefined()
    expect(wrapper.find('.review-feedback-fold__count').text()).toBe('1 of 3 written · optional')
    expect(wrapper.find('.review-feedback-fold__title').text()).toBe(
      'Notes and feedback for the entity',
    )
  })

  // Operator 2026-11-22: the notes looked gone while folded, so they stay open while editable.
  it('stays open while the review is editable', () => {
    const wrapper = mountFold({ strengths: '', improvements: '', notes: '' }, false)
    expect(wrapper.find('details').attributes('open')).toBeDefined()
  })

  it('opens by itself when a field is over its limit, so its error shows', () => {
    const wrapper = mountFold({ strengths: 'x'.repeat(5001), improvements: '', notes: '' })
    expect(wrapper.find('details').attributes('open')).toBeDefined()
  })
})

describe('ReviewEvidenceRows', () => {
  it("lists each requirement's files and flags a required one left empty", () => {
    const wrapper = mount(ReviewEvidenceRows, {
      props: {
        downloading: {},
        rows: [
          {
            key: 'plan',
            name: { en: 'Plan' },
            required: true,
            late: false,
            files: [{ id: 1, fileName: 'plan.pdf', sizeBytes: 1024 }],
          },
          { key: 'minutes', name: { en: 'Minutes' }, required: true, late: true, files: [] },
        ],
      },
      ...globals(),
    })
    const rows = wrapper.findAll('.review-rows__row')
    expect(rows[0].find('.review-evidence__name').text()).toBe('plan.pdf')
    expect(rows[1].find('.review-rows__missing').exists()).toBe(true)
    expect(rows[1].text()).toContain('added after submission')
  })
})
