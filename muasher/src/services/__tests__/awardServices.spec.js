import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'
import { awardCycleService } from '@/services/awardCycleService'
import { filteringService } from '@/services/filteringService'
import { advancedService } from '@/services/advancedService'
import { requestsService } from '@/services/requestsService'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

function answer(data, meta = {}) {
  return { data: { data, meta, errors: null } }
}

function rejection(status, code, meta = {}) {
  const error = new Error(code)
  error.response = { status }
  error.normalized = { status, code, meta, message: code, fieldErrors: {} }
  return error
}

const cycleWire = {
  id: 31,
  name: { ar: 'دورة الجائزة', en: 'Award cycle' },
  year: 2026,
  status: 'submission-open',
  criteriaVersionId: 1,
  scaleKind: 'of-10',
  submissionStart: '2026-10-01T00:00:00Z',
  submissionEnd: '2026-12-15T20:59:00Z',
  committeeStart: '2027-01-04T21:00:00Z',
  committeeEnd: '2027-02-15T20:59:00Z',
  announcementAt: null,
  conditions: { minEntityAgeYears: 3, disqualificationScore: '60.00' },
  initialReviewSlaDays: 14,
  participationCertificateEnabled: true,
  regions: [{ id: 1, code: 'riyadh', name: { ar: 'منطقة الرياض', en: 'Riyadh Region' } }],
  counts: { requests: 5, qualified: 0 },
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('awardCycleService', () => {
  it('lists cycles with their stats and reads a detail with or without the staff', async () => {
    apiClient.get.mockResolvedValue(
      answer([cycleWire], { pagination: { page: 1 }, stats: { total: 3, byStatus: { draft: 1 } } }),
    )
    const list = await awardCycleService.list({ 'filter[status]': 'submission-open' })
    expect(apiClient.get).toHaveBeenCalledWith('/award/cycles', {
      params: { 'filter[status]': 'submission-open' },
    })
    expect(list.data[0]).toMatchObject({
      id: 31,
      conditions: { disqualificationScore: '60.00' },
      regions: [{ code: 'riyadh' }],
      counts: { requests: 5 },
    })
    expect(list.meta.stats).toEqual({ total: 3, byStatus: { draft: 1 } })
    apiClient.get.mockResolvedValue(
      answer({
        ...cycleWire,
        canManage: true,
        members: [{ id: 13, name: 'بدر', role: 'filtering_officer' }],
        initialAssessors: [{ id: 10, name: 'سلطان', isActive: true }],
      }),
    )
    const detail = (await awardCycleService.get(31)).data
    expect(detail).toMatchObject({
      canManage: true,
      revealsStaff: true,
      members: [{ role: 'filtering_officer' }],
      initialAssessors: [{ isActive: true }],
    })
    apiClient.get.mockResolvedValue(answer({ ...cycleWire, canManage: false }))
    expect((await awardCycleService.get(31)).data).toMatchObject({
      revealsStaff: false,
      members: [],
      initialAssessors: [],
    })
  })

  it('reads the public cycle, the defaults and the candidates', async () => {
    apiClient.get.mockResolvedValue(answer(null))
    expect((await awardCycleService.getCurrent()).data).toBeNull()
    apiClient.get.mockResolvedValue(
      answer({
        id: 31,
        isAcceptingApplications: true,
        conditions: { minEntityAgeYears: 3 },
        regions: [],
      }),
    )
    expect((await awardCycleService.getCurrent()).data).toMatchObject({
      isAcceptingApplications: true,
      conditions: { minEntityAgeYears: 3 },
    })
    apiClient.get.mockResolvedValue(
      answer({ minEntityAgeYears: 3, disqualificationScore: '60.00', initialReviewSlaDays: 14 }),
    )
    expect((await awardCycleService.getDefaultConditions()).data.disqualificationScore).toBe(
      '60.00',
    )
    apiClient.get.mockResolvedValue(answer([{ id: 13, name: 'بدر' }]))
    expect((await awardCycleService.listCandidates({ role: 'filtering_officer' })).data).toEqual([
      { id: 13, name: 'بدر' },
    ])
    expect(apiClient.get).toHaveBeenLastCalledWith('/award/cycles/candidates', {
      params: { role: 'filtering_officer' },
    })
  })

  it('posts every write on its route and treats 409 as an expected answer', async () => {
    apiClient.post.mockResolvedValue(answer({ ...cycleWire, status: 'draft' }))
    apiClient.put.mockResolvedValue(answer(cycleWire))
    await awardCycleService.create({ regionIds: [1] })
    expect(apiClient.post).toHaveBeenCalledWith('/award/cycles', { regionIds: [1] })
    await awardCycleService.update(31, { announcementAt: null })
    await awardCycleService.syncMembers(31, { filteringOfficerIds: [13] })
    await awardCycleService.syncInitialAssessors(31, { assessorIds: [10] })
    expect(apiClient.put.mock.calls.map((call) => call[0])).toEqual([
      '/award/cycles/31',
      '/award/cycles/31/members',
      '/award/cycles/31/initial-assessors',
    ])
    await awardCycleService.open(32)
    await awardCycleService.closeSubmissions(31)
    await awardCycleService.close(31)
    await awardCycleService.reopen(30, { reason: 'سبب' })
    expect(apiClient.post.mock.calls.slice(1).map((call) => call[0])).toEqual([
      '/award/cycles/32/open',
      '/award/cycles/31/close-submissions',
      '/award/cycles/31/close',
      '/award/cycles/30/reopen',
    ])
    apiClient.post.mockRejectedValue(rejection(409, 'cycle.another_open', { awardCycleId: 31 }))
    await expect(awardCycleService.open(32)).rejects.toMatchObject({
      normalized: { code: 'cycle.another_open' },
    })
    expect(logger.error).not.toHaveBeenCalled()
  })
})

describe('advancedService and filteringService', () => {
  it('maps the eligibility, the condition failures and the previous request', async () => {
    apiClient.get.mockResolvedValue(
      answer({
        cycle: { id: 31, regions: [], conditions: { minEntityAgeYears: 3 } },
        eligibility: {
          eligible: false,
          windowOpen: true,
          conditions: [{ rule: 'region', passed: false, required: null, actual: null }],
        },
        request: {
          id: 9206,
          status: 'rejected-conditions',
          awardCycleId: 31,
          conditionFailure: { failures: [{ rule: 'region', passed: false }] },
        },
        previous: null,
      }),
    )
    const { data } = await advancedService.getCurrent()
    expect(data.eligibility).toEqual({
      eligible: false,
      windowOpen: true,
      conditions: [{ rule: 'region', passed: false, required: null, actual: null }],
    })
    expect(data.request).toMatchObject({
      status: 'rejected-conditions',
      awardCycleId: 31,
      conditionFailures: [{ rule: 'region', passed: false }],
    })
    expect(data.previous).toBeNull()
  })

  it('applies, deletes a draft and answers 422 conditions as expected', async () => {
    apiClient.post.mockResolvedValue(answer({ id: 9300, status: 'draft', awardCycleId: 31 }))
    expect((await advancedService.apply({ awardCycleId: 31 })).data).toMatchObject({
      id: 9300,
      awardCycleId: 31,
    })
    apiClient.delete.mockResolvedValue({ data: null })
    await advancedService.deleteDraft(9300)
    expect(apiClient.delete).toHaveBeenCalledWith('/assessments/9300', { data: {} })
    apiClient.post.mockRejectedValue(rejection(422, 'cycle.conditions_failed'))
    await expect(advancedService.apply({ awardCycleId: 31 })).rejects.toMatchObject({
      normalized: { code: 'cycle.conditions_failed' },
    })
    expect(logger.error).not.toHaveBeenCalled()
  })

  it('lists the filtering queue with its meta and posts the decisions', async () => {
    const row = {
      id: 9205,
      number: 'ADV-2026-000005',
      status: 'awaiting-filtering',
      initialResult: { total: '1', maxTotal: '2', percent: '57.50' },
      meetsThreshold: false,
      decision: null,
    }
    apiClient.get.mockResolvedValue(
      answer([row], {
        disqualificationScore: '60.00',
        counts: { awaitingFiltering: 2 },
        belowThreshold: 1,
        pagination: { page: 1 },
      }),
    )
    const list = await filteringService.list(31, { sort: '-initial_percent' })
    expect(apiClient.get).toHaveBeenCalledWith('/award/cycles/31/filtering', {
      params: { sort: '-initial_percent' },
    })
    expect(list.data[0]).toMatchObject({
      meetsThreshold: false,
      initialResult: { percent: '57.50' },
    })
    expect(list.meta).toMatchObject({
      disqualificationScore: '60.00',
      counts: { awaitingFiltering: 2, notQualified: 0 },
      belowThreshold: 1,
    })
    apiClient.post.mockResolvedValue(
      answer({
        ...row,
        status: 'qualified-for-committee',
        decision: { outcome: 'qualified', isOverride: true, decidedBy: { id: 13, name: 'بدر' } },
      }),
    )
    const qualified = await filteringService.qualify(31, 9205, { overrideReason: 'سبب' })
    expect(apiClient.post).toHaveBeenCalledWith('/award/cycles/31/filtering/9205/qualify', {
      overrideReason: 'سبب',
    })
    expect(qualified.data.decision).toMatchObject({
      outcome: 'qualified',
      isOverride: true,
      decidedBy: { id: 13 },
    })
    await filteringService.disqualify(31, 9205, { reason: 'سبب' })
    expect(apiClient.post).toHaveBeenLastCalledWith('/award/cycles/31/filtering/9205/disqualify', {
      reason: 'سبب',
    })
  })
})

describe('requestsService advanced actions', () => {
  it('returns for completion and withdraws, mapping the cycle and the region of the detail', async () => {
    apiClient.post.mockResolvedValue(
      answer({
        id: 9203,
        path: 'advanced',
        status: 'needs-completion',
        awardCycle: { id: 31, name: { ar: 'د', en: 'C' }, status: 'submission-open' },
        region: { id: 3, code: 'madinah', name: null },
        record: { statusHistory: [] },
        assignmentHistory: [],
        completionRequests: [],
      }),
    )
    const { data } = await requestsService.returnForCompletion(9203, { notes: 'n' })
    expect(apiClient.post).toHaveBeenCalledWith('/requests/9203/return-for-completion', {
      notes: 'n',
    })
    expect(data).toMatchObject({ awardCycle: { id: 31 }, region: { code: 'madinah' } })
    await requestsService.withdraw(9203, { reason: 'r' })
    expect(apiClient.post).toHaveBeenLastCalledWith('/requests/9203/withdraw', { reason: 'r' })
  })
})
