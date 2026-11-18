import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import ReviewEvidenceList from '@/components/review/ReviewEvidenceList.vue'
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
    const wrapper = mountWith(ReviewEvidenceList, { files: FILES, downloading: {}, slots: SLOTS })
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

  it('keeps the flat card list when the sub-criterion has no attachment requirements', () => {
    const wrapper = mountWith(ReviewEvidenceList, { files: FILES, downloading: {} })

    expect(wrapper.find('.review-evidence__group').exists()).toBe(false)
    expect(wrapper.findAll('.review-evidence__card')).toHaveLength(2)
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
  it('tells the assessor that an open request moved to a new criteria version', () => {
    const wrapper = mountWith(ReviewNotices, {
      review: {
        mode: 'edit',
        assessment: { status: 'under-review', isFrozen: false },
        criteriaUpdate: UPDATE,
      },
    })

    expect(wrapper.text()).toContain('This request moved from criteria version 1 to version 2')
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
