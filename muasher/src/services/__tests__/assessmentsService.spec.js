import { describe, expect, it, vi, beforeEach } from 'vitest'
import { assessmentsService } from '@/services/assessmentsService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const envelope = { data: {}, meta: {}, errors: null }

beforeEach(() => {
  vi.clearAllMocks()
})

const assessmentWire = {
  id: 9002,
  number: 'SLF-2026-000002',
  path: 'self',
  pathLabel: { ar: 'التقييم الذاتي', en: 'Self assessment' },
  status: 'draft',
  isOpen: true,
  isFrozen: false,
  criteriaUpdateInProgress: false,
  criteriaVersionId: 1,
  scaleKind: 'of-10',
  awardCycleId: null,
  entity: {
    id: 5001,
    reference: 'ENT-005001',
    name: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity — Riyadh' },
    status: 'active',
  },
  result: null,
  createdAt: '2026-10-28T11:15:00+00:00',
  updatedAt: '2026-10-29T09:30:00+00:00',
  submittedAt: null,
  completedAt: null,
}

const scoresWire = {
  assessment: assessmentWire,
  scale: { kind: 'of_10', maximum: '10.00' },
  scores: { subs: { 103: { rawValue: '7.50', percent: '75.00' } }, points: {} },
  progress: { scored: 1, required: 27 },
}

function rejection(status, code, meta = {}) {
  const error = new Error(code)
  error.response = { status }
  error.normalized = { status, code, meta, message: code, fieldErrors: {} }
  return error
}

describe('assessmentsService (self assessment contract)', () => {
  it('reads the published criteria and treats 404 as an expected answer', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await assessmentsService.getPublishedCriteria()
    expect(apiClient.get).toHaveBeenCalledWith('/assessments/criteria', { params: {} })
    const missing = rejection(404, 'criteria.no_published_version')
    apiClient.get.mockRejectedValue(missing)
    await expect(assessmentsService.getPublishedCriteria()).rejects.toBe(missing)
    expect(logger.error).not.toHaveBeenCalled()
  })

  it('lists with snake_case-ready params and maps every item', async () => {
    apiClient.get.mockResolvedValue({
      data: { data: [assessmentWire], meta: { pagination: { page: 1 } }, errors: null },
    })
    const params = { 'filter[path]': 'self', 'filter[status]': 'draft', perPage: 1 }
    const { data, meta } = await assessmentsService.list(params)
    expect(apiClient.get).toHaveBeenCalledWith('/assessments', { params })
    expect(data[0]).toEqual(assessmentWire)
    expect(meta.pagination.page).toBe(1)
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'assessments.list',
      expect.objectContaining({
        entity: 'assessment',
        paramsShape: ['filter[path]', 'filter[status]', 'perPage'],
      }),
    )
  })

  it('starts the self path with path self only', async () => {
    apiClient.post.mockResolvedValue({ data: { data: assessmentWire, meta: {}, errors: null } })
    const { data } = await assessmentsService.startSelf()
    expect(apiClient.post).toHaveBeenCalledWith('/assessments', { path: 'self' })
    expect(data.number).toBe('SLF-2026-000002')
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'assessments.startSelf',
      expect.objectContaining({ payloadShape: ['path'] }),
    )
  })

  it('does not report the expected 409 draft_exists rejection as an error', async () => {
    apiClient.post.mockRejectedValue(rejection(409, 'self.draft_exists', { assessmentId: 9002 }))
    await expect(assessmentsService.startSelf()).rejects.toMatchObject({
      normalized: { code: 'self.draft_exists' },
    })
    expect(logger.error).not.toHaveBeenCalled()
  })

  it('maps the form with the scale kind from its canonical value', async () => {
    apiClient.get.mockResolvedValue({
      data: {
        data: { ...scoresWire, criteria: { versionNumber: 1, mains: [] } },
        meta: {},
        errors: null,
      },
    })
    const { data } = await assessmentsService.getForm(9002)
    expect(apiClient.get).toHaveBeenCalledWith('/assessments/9002/form')
    expect(data.scale).toEqual({ kind: 'of-10', maximum: '10.00' })
    expect(data.scores.subs['103']).toEqual({ rawValue: '7.50', percent: '75.00' })
    expect(data.progress).toEqual({ scored: 1, required: 27 })
    expect(data.criteria.versionNumber).toBe(1)
  })

  it('saves the complete raw sheet via PUT and maps the scores answer', async () => {
    apiClient.put.mockResolvedValue({ data: { data: scoresWire, meta: {}, errors: null } })
    const payload = { scores: { subs: { 103: '7.5' }, points: { 1001: null } } }
    const { data } = await assessmentsService.saveDraft(9002, payload)
    expect(apiClient.put).toHaveBeenCalledWith('/assessments/9002', payload)
    expect(data.assessment.id).toBe(9002)
    expect(data).not.toHaveProperty('criteria')
  })

  it('submits via POST and maps the result answer', async () => {
    apiClient.post.mockResolvedValue({
      data: {
        data: {
          id: 9002,
          number: 'SLF-2026-000002',
          path: 'self',
          status: 'result-available',
          isFrozen: false,
          available: true,
          total: '6560.00',
          maxTotal: '8200.00',
          percent: '80.00',
          main: [],
          sub: [],
          points: [],
        },
        meta: {},
        errors: null,
      },
    })
    const { data } = await assessmentsService.submit(9002, { scores: { subs: {}, points: {} } })
    expect(apiClient.post).toHaveBeenCalledWith('/assessments/9002/submit', {
      scores: { subs: {}, points: {} },
    })
    expect(data).toMatchObject({ available: true, percent: '80.00', maxTotal: '8200.00' })
  })

  it('keeps the Q-94 placeholder minimal', async () => {
    apiClient.get.mockResolvedValue({
      data: {
        data: {
          id: 9002,
          number: 'SLF-2026-000002',
          path: 'self',
          status: 'draft',
          isFrozen: false,
          available: false,
        },
        meta: {},
        errors: null,
      },
    })
    const { data } = await assessmentsService.getResult(9002)
    expect(apiClient.get).toHaveBeenCalledWith('/assessments/9002/result')
    expect(data).toEqual({
      id: 9002,
      number: 'SLF-2026-000002',
      path: 'self',
      status: 'draft',
      isFrozen: false,
      available: false,
    })
  })

  it('reports unexpected failures to GlitchTip and rethrows (never swallows)', async () => {
    const failure = new Error('boom')
    apiClient.get.mockRejectedValue(failure)
    await expect(assessmentsService.list()).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'assessments.list' }),
    )
  })
})
