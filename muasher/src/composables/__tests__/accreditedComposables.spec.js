import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { accreditedService } from '@/services/accreditedService'
import { submissionService } from '@/services/submissionService'
import { evidenceService } from '@/services/evidenceService'
import { servicePricesService } from '@/services/servicePricesService'
import { useAccreditedForm } from '@/composables/useAccreditedForm'
import { useAccreditedIntro } from '@/composables/useAccreditedIntro'
import { useAccreditedRejection } from '@/composables/useAccreditedRejection'
import { useCvDrafts } from '@/composables/useCvDrafts'
import { useEvidenceUploads } from '@/composables/useEvidenceUploads'
import { useReasonPrompt } from '@/composables/useReasonPrompt'
import { useReviewFeedback } from '@/composables/useReviewFeedback'
import { useRequestDetail } from '@/composables/useRequestDetail'
import { requestsService } from '@/services/requestsService'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import { withSetup } from '@/test-utils/withSetup'

vi.mock('@/services/submissionService', () => ({
  submissionService: { getForm: vi.fn(), saveCv: vi.fn(), submit: vi.fn() },
}))
vi.mock('@/services/accreditedService', () => ({
  accreditedService: { getCurrent: vi.fn(), getResult: vi.fn() },
}))
vi.mock('@/services/evidenceService', () => ({
  evidenceService: { list: vi.fn(), upload: vi.fn(), remove: vi.fn(), requestDownload: vi.fn() },
}))
vi.mock('@/services/servicePricesService', () => ({
  servicePricesService: { getServicePrices: vi.fn() },
}))
vi.mock('@/services/requestsService', () => ({ requestsService: { get: vi.fn() } }))

function formOf(status, extra = {}) {
  return {
    assessment: {
      id: 9101,
      number: 'ACC-2026-000004',
      status,
      isFrozen: false,
      criteriaUpdateInProgress: false,
    },
    criteria: { mains: [] },
    cv: { 101: 'سيرة محفوظة' },
    evidence: [],
    missing: { missingCv: [], missingAttachments: [] },
    completionRequest:
      status === 'needs-completion' ? { items: [{ subCriterionId: 202, kind: 'cv' }] } : null,
    uploadLimits: { maxFileMb: 20, allowedTypes: ['pdf'], maxFilesPerSub: 10 },
    criteriaUpdate: null,
    ...extra,
  }
}

function failure(status, code, meta = {}) {
  return Object.assign(new Error(code), {
    response: { status },
    normalized: { status, code, meta, message: code, fieldErrors: {} },
  })
}

async function formState(status, extra) {
  submissionService.getForm.mockResolvedValue({ data: formOf(status, extra) })
  const { api } = withSetup(() => useAccreditedForm(ref(9101)))
  await api.load()
  return api
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useAccreditedForm', () => {
  it('lets the entity edit a draft and only the requested items during a completion request', async () => {
    const draft = await formState('draft')
    expect(draft.canEdit(101, 'cv')).toBe(true)
    const completion = await formState('needs-completion')
    expect(completion.canEdit(202, 'cv')).toBe(true)
    expect(completion.canEdit(202, 'evidence')).toBe(false)
    expect(completion.canEdit(101, 'cv')).toBe(false)
    completion.lock('completion.deadline_passed')
    expect(completion.canEdit(202, 'cv')).toBe(false)
    const submitted = await formState('submitted')
    expect(submitted.isEditable.value).toBe(false)
    const frozen = await formState('draft', {
      assessment: { id: 9101, status: 'draft', isFrozen: true },
    })
    expect(frozen.canEdit(101, 'cv')).toBe(false)
  })

  it('maps 404 to missing and 403 to blocked', async () => {
    submissionService.getForm.mockRejectedValue(failure(404, 'not_found'))
    const { api } = withSetup(() => useAccreditedForm(ref(1)))
    await api.load()
    expect(api.status.value).toBe('missing')
    submissionService.getForm.mockRejectedValue(failure(403, 'forbidden'))
    await api.load()
    expect(api.status.value).toBe('blocked')
  })
})

describe('useCvDrafts and useAccreditedRejection', () => {
  it('sends only the changed CV entries, null for a cleared one, and reloads the missing list', async () => {
    const state = await formState('draft')
    const rejected = vi.fn()
    const { api } = withSetup(() => useCvDrafts({ formState: state, onRejected: rejected }))
    api.resetFrom(state.form.value.cv)
    api.setDraft(101, '')
    api.setDraft(802, 'سيرة جديدة')
    expect(api.changes.value).toEqual({ 101: null, 802: 'سيرة جديدة' })
    submissionService.saveCv.mockResolvedValue({
      data: { assessment: state.assessment.value, cv: { 802: 'سيرة جديدة' } },
    })
    expect(await api.save()).toBe(true)
    expect(submissionService.saveCv).toHaveBeenCalledWith(9101, {
      cv: { 101: null, 802: 'سيرة جديدة' },
    })
    expect(submissionService.getForm).toHaveBeenCalledTimes(2)
    submissionService.saveCv.mockRejectedValue(failure(409, 'entity.deleted'))
    api.setDraft(802, 'تعديل')
    expect(await api.save()).toBe(false)
    expect(rejected).toHaveBeenCalled()
  })

  it('freezes, locks after the deadline and shows the missing items of a refused submission', async () => {
    const state = await formState('needs-completion')
    const { api } = withSetup(() => useAccreditedRejection(state))
    api.handle(failure(409, 'entity.deleted'))
    expect(state.assessment.value.isFrozen).toBe(true)
    api.handle(failure(409, 'completion.deadline_passed'))
    expect([api.conflictCode.value, state.lockedCode.value]).toEqual([
      'completion.deadline_passed',
      'completion.deadline_passed',
    ])
    api.handle(
      failure(422, 'submission.incomplete', { missingCv: [{ subCriterionId: 802, code: 'M8.2' }] }),
    )
    expect(state.form.value.missing).toEqual({
      missingCv: [{ subCriterionId: 802, code: 'M8.2' }],
      missingAttachments: [],
    })
  })
})

describe('useEvidenceUploads', () => {
  it('refuses a wrong type locally and uploads an allowed file into its slot', async () => {
    const state = await formState('draft')
    const { api } = withSetup(() => useEvidenceUploads({ formState: state, onRejected: vi.fn() }))
    await api.upload(802, 'sub_802_1', new File(['x'], 'notes.txt'))
    expect(api.errors['802:sub_802_1']).toContain('pdf')
    expect(evidenceService.upload).not.toHaveBeenCalled()
    evidenceService.upload.mockResolvedValue({
      data: {
        id: 'e9',
        fileName: 'impact.pdf',
        scanStatus: 'pending',
        subCriterionId: 802,
        attachmentKey: 'sub_802_1',
      },
    })
    const file = new File(['%PDF'], 'impact.pdf')
    await api.upload(802, 'sub_802_1', file)
    expect(evidenceService.upload).toHaveBeenCalledWith(9101, {
      file,
      subCriterionId: 802,
      attachmentKey: 'sub_802_1',
    })
    expect(submissionService.getForm).toHaveBeenCalledTimes(2)
  })
})

describe('review, reason prompt, intro and detail', () => {
  it('tracks changed feedback fields per sub-criterion', async () => {
    const review = ref({
      assessment: { id: 9108 },
      feedback: { 101: { strengths: 'قوة', improvementPoints: null, notes: null } },
    })
    const { api } = withSetup(() =>
      useReviewFeedback({ reviewState: { review, assessment: ref({ id: 9108 }), patch: vi.fn() } }),
    )
    api.resetFrom(review.value.feedback)
    expect(api.dirty.value).toBe(false)
    api.setField(101, 'strengths', '')
    api.setField(202, 'notes', 'ملاحظة')
    expect(api.changes.value).toEqual({ 101: { strengths: null }, 202: { notes: 'ملاحظة' } })
    expect(api.fieldsOf(303)).toEqual({ strengths: '', improvementPoints: '', notes: '' })
  })

  it('resolves the reason prompt with the reason, or null when closed', async () => {
    const { api } = withSetup(() => useReasonPrompt())
    const first = api.prompt()
    api.provide('سبب موثق للإجراء')
    await expect(first).resolves.toBe('سبب موثق للإجراء')
    const second = api.prompt()
    api.open.value = false
    await flushPromises()
    await expect(second).resolves.toBeNull()
  })

  it('loads the public price for guests and the current request for an entity', async () => {
    servicePricesService.getServicePrices.mockResolvedValue({
      data: { accredited: { total: '1150.00' }, bag: null },
    })
    const guest = withSetup(() => useAccreditedIntro())
    await guest.api.load()
    expect(accreditedService.getCurrent).not.toHaveBeenCalled()
    expect(guest.api.priceStatus.value).toBe('ready')
    const entity = withSetup(() => useAccreditedIntro())
    useAuthStore(entity.globals.pinia).setUser(sessionUserFor(['entity']))
    accreditedService.getCurrent.mockResolvedValue({
      data: { awaitingPayment: null, active: null, latestClosed: null },
    })
    servicePricesService.getServicePrices.mockRejectedValue(failure(503, 'service_unavailable'))
    await entity.api.load()
    expect(accreditedService.getCurrent).toHaveBeenCalled()
    expect([entity.api.status.value, entity.api.priceStatus.value]).toEqual(['ready', 'error'])
  })

  it('offers assignment for a submitted request and reassignment for an assigned one', async () => {
    requestsService.get.mockResolvedValue({
      data: { id: 9105, status: 'submitted', isFrozen: false },
    })
    const { api } = withSetup(() => useRequestDetail(ref(9105)))
    await api.load()
    expect([api.canAssign.value, api.canReassign.value]).toEqual([true, false])
    api.apply({ id: 9105, status: 'under-review', isFrozen: false })
    expect([api.canAssign.value, api.canReassign.value]).toEqual([false, true])
    api.apply({ id: 9105, status: 'under-review', isFrozen: true })
    expect(api.canReassign.value).toBe(false)
  })
})
