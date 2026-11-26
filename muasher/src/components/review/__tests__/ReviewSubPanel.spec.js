import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useForm } from 'vee-validate'
import ReviewSubPanel from '@/components/review/ReviewSubPanel.vue'
import { useReviewNotes } from '@/composables/useReviewNotes'
import { createTestGlobals } from '@/test-utils'

const MAIN = { id: 11, weight: '1000.00' }

const POINTS_SUB = {
  id: 21,
  code: 'M1.1',
  name: { ar: 'التوجه', en: 'Direction' },
  weight: '400.00',
  evaluationType: 'guiding-points',
  scoringType: 'score',
  hasCv: true,
  assessorNoteLabel: null,
  attachments: [{ key: 'plan', name: { en: 'Approved plan' }, required: true }],
  points: [
    {
      id: 31,
      code: 'M1.1.1',
      description: { ar: 'وثيقة', en: 'Published vision' },
      weight: '200.00',
      scoringType: 'score',
      attachments: [{ key: 'page', name: { en: 'Website page' }, required: true }],
    },
    {
      id: 32,
      code: 'M1.1.2',
      description: { ar: 'ربط', en: 'Linked goals' },
      weight: '200.00',
      scoringType: 'score',
      attachments: [{ key: 'cards', name: { en: 'KPI cards' }, required: true }],
    },
  ],
}

const DIRECT_SUB = {
  ...POINTS_SUB,
  id: 22,
  code: 'M1.2',
  name: { ar: 'الحوكمة', en: 'Governance' },
  evaluationType: 'direct',
  hasCv: false,
  points: [],
}

function evidence(subId, id, attachmentKey) {
  return {
    id,
    subCriterionId: subId,
    level: 'sub',
    attachmentKey,
    fileName: `${attachmentKey}.pdf`,
    sizeBytes: 2048,
    scanStatus: 'clean',
  }
}

function mountSub(sub, { values = {}, canScore = true, reviewNotes = null } = {}) {
  const setField = vi.fn()
  const Host = defineComponent({
    setup() {
      const form = useForm({ initialValues: values })
      const workspace = {
        reviewState: {
          review: ref({
            scores: { subs: {}, points: {} },
            cv: { 21: 'The board adopted the vision in 2024.' },
            evidence: [evidence(21, 1, 'plan'), evidence(21, 2, 'page')],
            lateAttachmentKeys: [],
            reviewNotes,
            canWriteReviewNotes: reviewNotes !== null,
          }),
          canScore: ref(canScore),
        },
        scoreSheet: { sheet: { values: form.values }, typeOf: (node) => node.scoringType },
        actions: { busy: ref('') },
        downloads: { busy: {} },
        feedback: { fieldsOf: () => ({ strengths: '', improvements: '', notes: '' }), setField },
      }
      workspace.notes = useReviewNotes({ reviewState: workspace.reviewState })
      return () => h(ReviewSubPanel, { sub, main: MAIN, workspace })
    },
  })
  return mount(Host, { global: { plugins: createTestGlobals({ locale: 'en' }).plugins } })
}

describe('ReviewSubPanel', () => {
  it("puts each indicator's score facing its title and its evidence under it", async () => {
    const wrapper = mountSub(POINTS_SUB)
    await flushPromises()
    expect(wrapper.find('.review-card__title').text()).toContain('M1.1')
    expect(wrapper.find('.review-card__meta').text()).toContain('40% of the criterion')
    expect(wrapper.find('.review-card__count').text()).toBe('0 / 2 indicators scored')
    const items = wrapper.findAll('.review-item')
    expect(items).toHaveLength(2)
    expect(items[0].find('.review-item__meta').text()).toContain('50% of the sub-criterion')
    expect(items[0].find('input').attributes('name')).toBe('point-31')
    expect(items[0].find('.review-evidence__name').text()).toBe('page.pdf')
    expect(items[1].find('.review-rows__missing').exists()).toBe(true)
    expect(
      wrapper.find('.review-card__body > .review-card__section .review-evidence__name').text(),
    ).toBe('plan.pdf')
  })

  it('turns a scored indicator and a fully scored card green', async () => {
    const wrapper = mountSub(POINTS_SUB, { values: { 'point-31': '8', 'point-32': '' } })
    await flushPromises()
    const items = wrapper.findAll('.review-item')
    expect(items[0].classes()).toContain('is-scored')
    expect(items[1].classes()).not.toContain('is-scored')
    expect(wrapper.find('.review-card').classes()).not.toContain('is-scored')
    await items[1].find('input').setValue('6')
    await flushPromises()
    expect(wrapper.find('.review-card').classes()).toContain('is-scored')
    expect(wrapper.find('.review-card__count').text()).toBe('2 / 2 indicators scored')
  })

  it('scores a direct sub-criterion in its title row', () => {
    const wrapper = mountSub(DIRECT_SUB)
    expect(wrapper.find('.review-card__head input').attributes('name')).toBe('sub-22')
    expect(wrapper.find('.review-item').exists()).toBe(false)
    expect(wrapper.find('.review-folded').exists()).toBe(false)
  })

  it('judges a direct sub-criterion once and lists its indicators with their evidence', async () => {
    const wrapper = mountSub({ ...POINTS_SUB, evaluationType: 'direct' })
    await flushPromises()
    expect(wrapper.find('.review-card__head input').attributes('name')).toBe('sub-21')
    expect(wrapper.find('.review-card__count').exists()).toBe(false)
    const rows = wrapper.findAll('.review-item--descriptive')
    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('Published vision')
    expect(rows[0].find('input').exists()).toBe(false)
    expect(rows[0].find('.review-evidence__name').text()).toBe('page.pdf')
    expect(rows[1].find('.review-rows__missing').exists()).toBe(true)
    expect(wrapper.text()).toContain('the indicators and their evidence are there to verify it')
  })

  it('folds the CV and the feedback, and locks the scores before the review starts', () => {
    const wrapper = mountSub(POINTS_SUB, { canScore: false })
    expect(wrapper.find('.review-folded__text').text()).toBe(
      'The board adopted the vision in 2024.',
    )
    expect(wrapper.find('details.review-feedback-fold').attributes('open')).toBeUndefined()
    expect(
      wrapper.findAll('input').every((input) => input.attributes('disabled') !== undefined),
    ).toBe(true)
  })

  it('shows no internal notes to a viewer who may not read them', () => {
    const wrapper = mountSub(POINTS_SUB)
    expect(wrapper.find('.review-note').exists()).toBe(false)
  })

  // Operator 2026-11-22: internal notes on the sub-criterion and on every indicator.
  it('offers an internal note on the sub-criterion and on each indicator', async () => {
    const mine = {
      id: 1,
      level: 'point',
      mainCriterionId: 11,
      subCriterionId: 21,
      guidingPointId: 31,
      body: 'Check the 2024 board minutes.',
      author: { id: 7001, name: 'Me', role: 'assessor' },
      isMine: true,
      updatedAt: '2026-11-22T10:00:00Z',
    }
    const wrapper = mountSub(POINTS_SUB, { reviewNotes: [mine] })
    await flushPromises()
    const items = wrapper.findAll('.review-item')
    expect(items[0].find('textarea').element.value).toBe('Check the 2024 board minutes.')
    expect(items[1].find('[data-testid="review-note-toggle-point-11-21-32"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="review-note-toggle-sub-11-21-0"]').text()).toBe(
      'Add an internal note',
    )
  })

  it('opens the note of an item marked «not achieved» for the reason, and folds it when achieved', async () => {
    const sub = { ...DIRECT_SUB, scoringType: 'achievement' }
    const notAchieved = mountSub(sub, { values: { 'sub-22': '0' }, reviewNotes: [] })
    await flushPromises()
    expect(notAchieved.find('.review-note.is-reason textarea').exists()).toBe(true)
    expect(notAchieved.find('.review-note label').text()).toContain('Why it is not achieved')
    const achieved = mountSub(sub, { values: { 'sub-22': '1' }, reviewNotes: [] })
    await flushPromises()
    expect(achieved.find('.review-note textarea').exists()).toBe(false)
  })

  it('keeps the notes and feedback open while the assessor can score', () => {
    const wrapper = mountSub(POINTS_SUB)
    expect(wrapper.find('details.review-feedback-fold').attributes('open')).toBeDefined()
    expect(wrapper.find('textarea').attributes('disabled')).toBeUndefined()
  })
})
