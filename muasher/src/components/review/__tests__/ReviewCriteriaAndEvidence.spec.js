import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import ReviewEvidenceList from '@/components/review/ReviewEvidenceList.vue'
import MainEvidenceReview from '@/components/review/MainEvidenceReview.vue'
import ReviewFeedbackFields from '@/components/review/ReviewFeedbackFields.vue'
import ReviewNotices from '@/components/review/ReviewNotices.vue'
import AccreditedNotices from '@/components/accredited/AccreditedNotices.vue'
import { createTestGlobals } from '@/test-utils'

function mountWith(component, props, locale = 'en') {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  return mount(component, { props, global: globals })
}

const SLOTS = [
  { key: 'policy', name: { ar: 'السياسة المعتمدة', en: 'Approved policy' }, required: true },
  { key: 'minutes', name: { ar: 'المحاضر', en: 'Board minutes' }, required: false },
  { key: 'report', name: { ar: 'التقرير السنوي', en: 'Annual report' }, required: true },
]

const FILES = [
  { id: 1, attachmentKey: 'policy', fileName: 'policy.pdf', sizeBytes: 2048 },
  { id: 2, attachmentKey: null, fileName: 'extra.docx', sizeBytes: 1024 },
]

const UPDATE = {
  fromVersionNumber: 1,
  toVersionNumber: 2,
  movedAt: '2026-11-18T09:00:00Z',
  scope: 'reset_open',
  trigger: 'criteria_edit',
  kept: [],
  reset: [{ level: 'sub', code: 'M1.1', label: { ar: 'السياسات', en: 'Policies' } }],
  added: [],
}

describe('ReviewEvidenceList', () => {
  it('groups the files under their required attachments and flags a missing required one', () => {
    const wrapper = mountWith(ReviewEvidenceList, {
      files: FILES,
      downloading: {},
      groups: [{ key: 'own', point: null, slots: SLOTS }],
    })
    const groups = wrapper.findAll('.review-evidence__group')

    expect(groups).toHaveLength(3)
    expect(groups[0].text()).toContain('Approved policy')
    expect(groups[0].text()).toContain('Required')
    expect(groups[0].findAll('.review-evidence__card')).toHaveLength(1)
    expect(groups[1].text()).toContain('Annual report')
    expect(groups[1].text()).toContain('The entity did not attach this file.')
    expect(groups[2].text()).toContain('Other attachments')
    expect(groups[2].text()).toContain('extra.docx')
    expect(wrapper.text()).not.toContain('Board minutes')
  })

  it('lists indicator attachments under their indicator and marks a required one added after the submission', () => {
    const point = { id: 7, description: { ar: 'نشر الخطة', en: 'Plan shared with staff' } }
    const survey = {
      key: 'survey',
      name: { ar: 'الاستبيان', en: 'Survey results' },
      required: true,
    }
    const wrapper = mountWith(ReviewEvidenceList, {
      files: FILES,
      downloading: {},
      groups: [
        { key: 'own', point: null, slots: SLOTS },
        { key: 'point-7', point, slots: [survey] },
      ],
      lateKeys: ['survey'],
    })

    expect(wrapper.find('.review-evidence__indicator').text()).toBe(
      'Indicator attachments: Plan shared with staff',
    )
    const late = wrapper
      .findAll('.review-evidence__group')
      .find((group) => group.text().includes('Survey results'))
    expect(late.text()).toContain('Required attachment added after submission')
    expect(wrapper.text().match(/added after submission/g)).toHaveLength(1)
  })

  it('keeps the flat card list when the sub-criterion has no attachment requirements', () => {
    const wrapper = mountWith(ReviewEvidenceList, { files: FILES, downloading: {} })

    expect(wrapper.find('.review-evidence__group').exists()).toBe(false)
    expect(wrapper.findAll('.review-evidence__card')).toHaveLength(2)
  })
})

describe('MainEvidenceReview', () => {
  it("shows the main criterion's own files and marks a required attachment added after the submission", () => {
    const plan = { key: 'plan', name: { ar: 'الخطة', en: 'Strategic plan' }, required: true }
    const wrapper = mountWith(MainEvidenceReview, {
      main: { id: 1, attachments: [plan] },
      evidence: [
        { id: 1, level: 'main', mainCriterionId: 1, attachmentKey: 'plan', fileName: 'plan.pdf' },
        { id: 2, level: 'sub', subCriterionId: 1, attachmentKey: 'plan', fileName: 'sub.pdf' },
      ],
      downloading: {},
      lateKeys: ['plan'],
    })

    expect(wrapper.find('.review-main-evidence__title').text()).toBe('Main criterion evidence')
    expect(wrapper.findAll('.review-evidence__card')).toHaveLength(1)
    expect(wrapper.text()).toContain('plan.pdf')
    expect(wrapper.text()).toContain('Required attachment added after submission')
  })
})

describe('MainEvidenceReview without anything to review', () => {
  it('stays hidden when the main criterion has optional attachments only and no files', () => {
    const photos = { key: 'photos', name: { ar: 'الصور', en: 'Photos' }, required: false }
    const wrapper = mountWith(MainEvidenceReview, {
      main: { id: 1, attachments: [photos] },
      evidence: [],
      downloading: {},
    })

    expect(wrapper.find('.review-main-evidence').exists()).toBe(false)
  })
})

describe('ReviewFeedbackFields', () => {
  it('shows the configured assessor note prompt above the notes field only', () => {
    const wrapper = mountWith(ReviewFeedbackFields, {
      subId: 101,
      fields: { strengths: '', improvementPoints: '', notes: '' },
      noteLabel: 'Describe the governance compliance level',
    })

    expect(wrapper.text().match(/Describe the governance compliance level/g)).toHaveLength(1)
  })
})

describe('criteria change banners', () => {
  it('tells the assessor that an open request moved to a new criteria version', async () => {
    const wrapper = mountWith(ReviewNotices, {
      review: {
        mode: 'edit',
        assessment: { status: 'under-review', isFrozen: false },
        criteriaUpdate: UPDATE,
      },
    })

    expect(wrapper.text()).toContain('This request moved from criteria version 1 to version 2')
    await wrapper.find('.rebind-notice [aria-expanded]').trigger('click')
    expect(wrapper.text()).toContain('applied to open requests')
    expect(wrapper.text()).toContain('Policies')
  })

  it('hides the banner once the request is completed', () => {
    const wrapper = mountWith(ReviewNotices, {
      review: {
        mode: 'read-only',
        assessment: { status: 'completed', isFrozen: false },
        criteriaUpdate: UPDATE,
      },
    })

    expect(wrapper.text()).not.toContain('moved from criteria version')
  })

  it('tells the entity in Arabic that its accredited request moved', () => {
    const wrapper = mountWith(
      AccreditedNotices,
      {
        assessment: { status: 'draft', path: 'accredited', isFrozen: false },
        criteriaUpdate: UPDATE,
      },
      'ar',
    )

    expect(wrapper.text()).toContain('انتقل طلبك من إصدار المعايير 1 إلى الإصدار 2')
  })
})
