import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import ScanStatusBadge from '../ScanStatusBadge.vue'
import EvidenceFileItem from '../EvidenceFileItem.vue'
import EvidenceSlotGroup from '../EvidenceSlotGroup.vue'
import CompletionRequestCard from '../CompletionRequestCard.vue'
import AccreditedCurrentCard from '../AccreditedCurrentCard.vue'
import AccreditedFeedbackList from '../AccreditedFeedbackList.vue'
import AccreditedCompleteness from '../AccreditedCompleteness.vue'
import CvEntryField from '../CvEntryField.vue'

function render(component, props, locale = 'en') {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  return mount(component, { props, global: { plugins: globals.plugins } })
}

const FILE = { id: 'e1', subCriterionId: 101, code: 'M1.1', attachmentKey: 'sub_101_1', fileName: 'vision.pdf', mimeType: 'application/pdf', sizeBytes: 204800, scanStatus: 'clean', uploadedAt: '2026-10-01T08:00:00Z' }

const SUB = {
  id: 101,
  code: 'M1.1',
  attachments: [{ key: 'sub_101_1', name: { ar: 'وثيقة الرؤية', en: 'Vision document' }, required: true }],
  points: [{ id: 1001, code: 'M1.1.1', attachments: [{ key: 'point_1001_1', name: { ar: 'نشر الرؤية', en: 'Vision publication' }, required: true }] }],
}

const UPLOADS = { uploading: {}, removing: {}, errors: {}, downloading: {} }
const LIMITS = { maxFileMb: 20, maxFilesPerSub: 2, allowedTypes: ['pdf', 'png'] }

describe('evidence components', () => {
  it('labels every scan state with text and an icon, never colour alone', () => {
    const labels = ['pending', 'clean', 'infected', 'failed'].map((status) => render(ScanStatusBadge, { status }).text())
    expect(labels).toEqual(['Being scanned', 'Clean', 'Refused', 'Scan failed'])
    expect(render(ScanStatusBadge, { status: 'infected' }, 'ar').find('i').exists()).toBe(true)
  })

  it('offers the download of a clean file and the replacement of a refused one', async () => {
    const clean = render(EvidenceFileItem, { file: FILE, editable: true })
    expect(clean.find('[aria-label="Download vision.pdf"]').exists()).toBe(true)
    expect(clean.find('.evidence-file__refusal').exists()).toBe(false)
    const refused = render(EvidenceFileItem, { file: { ...FILE, scanStatus: 'infected' }, editable: true })
    expect(refused.find('[aria-label="Download vision.pdf"]').exists()).toBe(false)
    expect(refused.find('.evidence-file__refusal').text()).toContain('Replace it')
    const replacement = new File(['%PDF'], 'vision-v2.pdf')
    const input = refused.find('.evidence-upload__input')
    Object.defineProperty(input.element, 'files', { value: [replacement] })
    await input.trigger('change')
    expect(refused.emitted('replace')[0][0]).toEqual({ file: { ...FILE, scanStatus: 'infected' }, replacement })
    const readOnly = render(EvidenceFileItem, { file: FILE, editable: false })
    expect(readOnly.find('[aria-label="Remove vision.pdf"]').exists()).toBe(false)
  })

  it('shows sub-criterion and guiding-point slots with required and provided states and the per-sub limit', () => {
    const wrapper = render(EvidenceSlotGroup, { sub: SUB, evidence: [FILE, { ...FILE, id: 'e2', attachmentKey: null, scanStatus: 'pending' }], editable: true, limits: LIMITS, uploads: UPLOADS })
    const slots = wrapper.findAll('.evidence-slots__slot')
    expect(slots).toHaveLength(3)
    expect(slots[0].text()).toContain('Provided')
    expect(slots[1].text()).toContain('M1.1.1')
    expect(slots[1].text()).toContain('Required')
    expect(slots[2].text()).toContain('Optional')
    expect(wrapper.find('.evidence-slots__full').exists()).toBe(true)
    expect(wrapper.findAll('.evidence-upload button').every((button) => button.attributes('disabled') !== undefined)).toBe(true)
  })
})

describe('completion, current request and result components', () => {
  const REQUEST = {
    id: 7,
    status: 'expired-continued',
    notes: 'يرجى إرفاق القوائم المالية المدققة.',
    items: [{ subCriterionId: 402, code: 'M4.2', kind: 'evidence', note: 'القوائم المدققة لعام 2025' }],
    deadlineAt: '2026-11-12T20:59:00Z',
    expiryOutcome: 'decline',
    requestedAt: '2026-10-28T09:00:00Z',
    resolvedAt: '2026-11-12T21:00:00Z',
  }

  it('shows the notes, items, deadline with the Hijri date, the expiry outcome and the final status', () => {
    const wrapper = render(CompletionRequestCard, { request: REQUEST, showStatus: true })
    expect(wrapper.text()).toContain('The request is closed as incomplete')
    expect(wrapper.text()).toContain('Expired, review continued')
    expect(wrapper.text()).toContain('M4.2')
    expect(wrapper.find('.date-stamp__hijri').text()).toMatch(/1448/)
    expect(wrapper.text()).not.toMatch(/[\u0660-\u0669]/)
  })

  it('shows the order in progress, the active request and explains a declined request', () => {
    const wrapper = render(AccreditedCurrentCard, {
      current: {
        awaitingPayment: { paymentId: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000011', reference: 'PAY-2026-000021', status: 'created', expiresAt: '2026-11-01T10:30:00Z' },
        active: null,
        latestClosed: { id: 9107, number: 'ACC-2026-000007', status: 'declined' },
      },
    })
    expect(wrapper.text()).toContain('PAY-2026-000021')
    expect(wrapper.text()).toContain('Closed as incomplete')
    expect(wrapper.text()).toContain('not provided before the deadline')
    expect(wrapper.findAll('a').map((link) => link.attributes('href'))).toEqual(['/payments/3f0c9a52-6d1e-4b7a-9c21-5a1e00000011'])
  })

  it('lists the written feedback per sub-criterion and says when none was written', () => {
    const wrapper = render(AccreditedFeedbackList, {
      feedback: [
        { subId: 101, code: 'M1.1', name: { ar: 'التوجه الاستراتيجي', en: 'Strategic direction' }, percent: '81.67', strengths: 'رؤية واضحة', improvementPoints: null, notes: null },
        { subId: 102, code: 'M1.2', name: { ar: 'الحوكمة', en: 'Governance' }, percent: '70.00', strengths: null, improvementPoints: null, notes: null },
      ],
    })
    expect(wrapper.findAll('.accredited-feedback__field')).toHaveLength(1)
    expect(wrapper.find('.accredited-feedback__field--strengths').text()).toContain('Strengths')
    expect(wrapper.find('.accredited-feedback__empty').exists()).toBe(true)
  })

  it('lists the missing items from the server and reveals the sub-criterion', async () => {
    const wrapper = render(AccreditedCompleteness, {
      missing: { missingCv: [{ subCriterionId: 101, code: 'M1.1' }], missingAttachments: [{ subCriterionId: 101, code: 'M1.1', attachmentKey: 'point_1001_1' }] },
      criteria: { mains: [{ subs: [SUB] }] },
    })
    expect(wrapper.text()).toContain('2 items are missing')
    expect(wrapper.text()).toContain('Attachment: Vision publication')
    await wrapper.find('.accredited-completeness__link').trigger('click')
    expect(wrapper.emitted('reveal')[0]).toEqual([101])
  })

  it('flags a missing CV entry only while the draft is empty', () => {
    expect(render(CvEntryField, { subId: 802, code: 'M8.2', modelValue: '', editable: true, missing: true }).find('.field__error').exists()).toBe(true)
    expect(render(CvEntryField, { subId: 802, code: 'M8.2', modelValue: 'نص', editable: true, missing: true }).find('.field__error').exists()).toBe(false)
    expect(render(CvEntryField, { subId: 802, code: 'M8.2', modelValue: '', editable: false, missing: true }).find('textarea').attributes('disabled')).toBeDefined()
  })
})
