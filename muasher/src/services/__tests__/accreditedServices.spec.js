import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'
import { accreditedService } from '@/services/accreditedService'
import { submissionService } from '@/services/submissionService'
import { evidenceService } from '@/services/evidenceService'
import { requestsService } from '@/services/requestsService'
import { assessorReviewService } from '@/services/assessorReviewService'
import { assessmentOverridesService } from '@/services/assessmentOverridesService'
import { assessmentSettingsService } from '@/services/assessmentSettingsService'
import { servicePricesService } from '@/services/servicePricesService'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

function answer(data, meta = {}) {
  return { data: { data, meta, errors: null } }
}

function rejection(status, code) {
  const error = new Error(code)
  error.response = { status }
  error.normalized = { status, code, meta: {}, message: code, fieldErrors: {} }
  return error
}

const assessment = {
  id: 9101,
  number: 'ACC-2026-000004',
  path: 'accredited',
  status: 'declined-incomplete',
  isOpen: false,
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('accreditedService', () => {
  it('reads the current request and maps the declined state through the one status mapper', async () => {
    apiClient.get.mockResolvedValue(
      answer({ awaitingPayment: null, active: null, latestClosed: assessment }),
    )
    const { data } = await accreditedService.getCurrent()
    expect(apiClient.get).toHaveBeenCalledWith('/assessments/accredited/current')
    expect(data.latestClosed.status).toBe('declined')
    expect(data.active).toBeNull()
  })

  it('maps the form with evidence, missing items, the completion request and its expiry outcome', async () => {
    apiClient.get.mockResolvedValue(
      answer({
        assessment: { ...assessment, status: 'needs-completion' },
        criteria: { mains: [] },
        cv: { 101: 'نص' },
        evidence: [
          {
            id: 'e1',
            subCriterionId: 101,
            code: 'M1.1',
            attachmentKey: null,
            fileName: 'a.pdf',
            mimeType: 'application/pdf',
            sizeBytes: 10,
            scanStatus: 'infected',
            uploadedAt: '2026-10-01T00:00:00Z',
          },
        ],
        missing: { missingCv: [{ subCriterionId: 802, code: 'M8.2' }], missingAttachments: [] },
        completionRequest: {
          id: 7,
          status: 'expired-declined',
          notes: 'n',
          items: [{ subCriterionId: 202, code: 'M2.2', kind: 'cv', note: null }],
          deadlineAt: '2026-11-12T20:59:00Z',
          expiryOutcome: 'decline',
          requestedAt: 'x',
          resolvedAt: null,
        },
        uploadLimits: { maxFileMb: 20, allowedTypes: ['pdf'], maxFilesPerSub: 10 },
        criteriaUpdate: null,
      }),
    )
    const { data } = await submissionService.getForm(9106)
    expect(apiClient.get).toHaveBeenCalledWith('/assessments/9106/form')
    expect(data.cv).toEqual({ 101: 'نص' })
    expect(data.evidence[0]).toMatchObject({ scanStatus: 'infected', subCriterionId: 101 })
    expect(data.completionRequest).toMatchObject({
      status: 'expired-declined',
      expiryOutcome: 'decline',
    })
    expect(data.missing.missingCv).toHaveLength(1)
  })

  it('saves CV entries and submits, treating 409 and 422 as expected answers', async () => {
    apiClient.put.mockResolvedValue(answer({ assessment, cv: { 802: 'x' } }))
    await submissionService.saveCv(9101, { cv: { 802: 'x' } })
    expect(apiClient.put).toHaveBeenCalledWith('/assessments/9101/cv', { cv: { 802: 'x' } })
    const breadcrumb = logger.breadcrumb.mock.calls.find((call) => call[1] === 'submission.saveCv')
    expect(JSON.stringify(breadcrumb)).not.toContain('"x"')
    apiClient.post.mockRejectedValue(rejection(422, 'submission.incomplete'))
    await expect(submissionService.submit(9101)).rejects.toMatchObject({
      normalized: { code: 'submission.incomplete' },
    })
    expect(logger.error).not.toHaveBeenCalled()
  })
})

describe('evidenceService', () => {
  it('uploads multipart with the slot key and asks for signed downloads and bundles', async () => {
    apiClient.post.mockResolvedValue(
      answer({ id: 'e2', scanStatus: 'pending', subCriterionId: 802 }),
    )
    const file = new File(['%PDF'], 'impact.pdf', { type: 'application/pdf' })
    const { data } = await evidenceService.upload(9101, {
      file,
      subCriterionId: 802,
      attachmentKey: 'sub_802_1',
    })
    const [path, form] = apiClient.post.mock.calls[0]
    expect(path).toBe('/assessments/9101/evidence')
    expect(form.get('subCriterionId')).toBe('802')
    expect(form.get('attachmentKey')).toBe('sub_802_1')
    expect(data.scanStatus).toBe('pending')
    apiClient.post.mockResolvedValue(
      answer({ id: 'b1', status: 'preparing', isDownloadable: false }),
    )
    expect((await evidenceService.requestBundle(9108)).data).toMatchObject({
      status: 'preparing',
      isDownloadable: false,
    })
    await evidenceService.requestDownload(9101, 'e2')
    expect(apiClient.post).toHaveBeenCalledWith('/assessments/9101/evidence/e2/download')
    apiClient.delete.mockResolvedValue({ data: null })
    await evidenceService.remove(9101, 'e2')
    expect(apiClient.delete).toHaveBeenCalledWith('/assessments/9101/evidence/e2', { data: {} })
  })
})

describe('requestsService', () => {
  it('sends the wire name of the declined filter and maps rows and stats', async () => {
    apiClient.get.mockResolvedValue(
      answer(
        [
          {
            id: 9105,
            status: 'declined-incomplete',
            entity: null,
            assessor: null,
            isOverdue: true,
          },
        ],
        {
          pagination: { page: 1 },
          stats: {
            total: 1,
            byStatus: { submitted: 0, declinedIncomplete: 1, underReview: 2 },
            overdue: 1,
          },
        },
      ),
    )
    const envelope = await requestsService.listQueue({ 'filter[status]': 'declined' })
    expect(apiClient.get).toHaveBeenCalledWith('/requests', {
      params: { 'filter[status]': 'declined-incomplete' },
    })
    expect(envelope.data[0]).toMatchObject({ status: 'declined', isOverdue: true })
    expect(envelope.meta.stats.byStatus).toMatchObject({
      declined: 1,
      'under-review': 2,
      submitted: 0,
    })
  })

  it('keeps the entity level on queue rows (null when the entity holds none)', async () => {
    apiClient.get.mockResolvedValue(
      answer([
        {
          id: 9101,
          status: 'submitted',
          entity: { id: 6002, reference: 'ENT-006002', name: { ar: 'إحسان' }, level: 'gold' },
        },
        { id: 9102, status: 'submitted', entity: { id: 6003, reference: 'ENT-006003' } },
      ]),
    )
    const { data } = await requestsService.listQueue()
    expect(data[0].entity).toMatchObject({ id: 6002, level: 'gold' })
    expect(data[1].entity.level).toBeNull()
  })

  it('maps the detail with assignment history and assigns or reassigns', async () => {
    apiClient.post.mockResolvedValue(
      answer({
        id: 9108,
        status: 'under-review',
        payment: { paid: true },
        record: { id: 9108, statusHistory: [] },
        assignmentHistory: [
          {
            id: 1,
            assessor: { id: 11, name: 'هند' },
            status: 'active',
            reason: 'r',
            assignedBy: null,
            endReason: null,
          },
        ],
        completionRequests: [],
      }),
    )
    const { data } = await requestsService.reassign(9108, { assessorId: 11, reason: 'r' })
    expect(apiClient.post).toHaveBeenCalledWith('/requests/9108/reassign', {
      assessorId: 11,
      reason: 'r',
    })
    expect(data.assignmentHistory[0]).toMatchObject({ reason: 'r', assignedBy: null })
    apiClient.get.mockResolvedValue(answer([{ id: 10, name: 'سلطان', openRequests: 2 }]))
    expect((await requestsService.listAssessors({ assessmentId: 9108 })).data).toEqual([
      { id: 10, name: 'سلطان', openRequests: 2 },
    ])
  })
})

describe('assessor review, overrides, settings and prices', () => {
  it('reads the review and posts every verb on its route', async () => {
    apiClient.get.mockResolvedValue(
      answer({
        assessment,
        mode: 'edit',
        scale: { kind: 'of_10', maximum: '10.00' },
        scores: { subs: {}, points: {} },
        progress: { scored: 0, required: 26 },
        feedback: { 101: { strengths: 'x', improvementPoints: null, notes: null } },
        evidence: [],
        cv: {},
        context: [],
      }),
    )
    const { data } = await assessorReviewService.getReview(9108)
    expect(data).toMatchObject({
      mode: 'edit',
      feedback: { 101: { strengths: 'x', improvementPoints: null, notes: null } },
    })
    apiClient.post.mockResolvedValue(answer(assessment))
    apiClient.put.mockResolvedValue(answer({ assessment, feedback: {} }))
    await assessorReviewService.startReview(9108)
    await assessorReviewService.requestCompletion(9108, {
      notes: 'n',
      items: [],
      deadlineAt: 'd',
      expiryOutcome: 'decline',
    })
    await assessorReviewService.complete(9108)
    await assessorReviewService.saveFeedback(9108, { feedback: {} })
    expect(apiClient.post.mock.calls.map((call) => call[0])).toEqual([
      '/assessments/9108/start-review',
      '/assessments/9108/request-completion',
      '/assessments/9108/complete',
    ])
    expect(apiClient.put).toHaveBeenCalledWith('/assessments/9108/review/feedback', {
      feedback: {},
    })
  })

  it('posts overrides with the canonical target, reads settings and the public prices', async () => {
    apiClient.post.mockResolvedValue(answer(assessment))
    await assessmentOverridesService.forceTransition(9105, {
      toStatus: 'under-review',
      reason: 'r',
    })
    expect(apiClient.post).toHaveBeenCalledWith('/assessments/9105/transition/force', {
      toStatus: 'under-review',
      reason: 'r',
    })
    apiClient.get.mockResolvedValue(
      answer({
        accreditedReviewSlaDays: 14,
        uploadMaxFileMb: 20,
        generalUploadMaxFileMb: 25,
        advancedMinEntityAgeYears: 3,
      }),
    )
    expect((await assessmentSettingsService.get()).data).toEqual({
      generalUploadMaxFileMb: 25,
      advancedMinEntityAgeYears: 3,
      advancedDisqualificationScore: null,
      committeeEntityDataVisibility: null,
    })
    apiClient.get.mockResolvedValue(
      answer({
        accredited: {
          priceBeforeVat: '1000.00',
          vatRate: '15.00',
          vatAmount: '150.00',
          total: '1150.00',
          currency: 'SAR',
        },
        bag: null,
      }),
    )
    const prices = await servicePricesService.getServicePrices()
    expect(apiClient.get).toHaveBeenLastCalledWith('/content/prices')
    expect(prices.data).toEqual({
      accredited: {
        priceBeforeVat: '1000.00',
        vatRate: '15.00',
        vatAmount: '150.00',
        total: '1150.00',
        currency: 'SAR',
      },
      bag: null,
    })
  })

  it('reports unexpected failures to GlitchTip and rethrows', async () => {
    const failure = new Error('boom')
    apiClient.get.mockRejectedValue(failure)
    await expect(requestsService.get(9108)).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'requests.get' }),
    )
  })
})
