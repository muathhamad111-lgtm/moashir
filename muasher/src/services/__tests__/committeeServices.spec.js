import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'
import { committeeService } from '@/services/committeeService'
import { committeeWorkService } from '@/services/committeeWorkService'
import { secretaryService } from '@/services/secretaryService'
import { awardResultsService } from '@/services/awardResultsService'
import { awardCycleService } from '@/services/awardCycleService'
import { assessmentsService } from '@/services/assessmentsService'
import { criteriaService } from '@/services/criteriaService'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

function answer(data, meta = {}) {
  return { data: { data, meta, errors: null } }
}

const member = {
  id: 7001,
  user: { id: 27, name: 'David Nguyen' },
  status: 'disabled',
  isSubstitute: false,
  replacesId: null,
  replacedById: 7010,
  appointedAt: '2026-10-20T08:00:00Z',
  disabledAt: '2026-10-30T08:00:00Z',
  disabledReason: 'إجازة طويلة',
  removedAt: null,
  progress: { notStarted: 1, inProgress: 0, completed: 2, voided: 0 },
}
const row = {
  id: 7101,
  assessmentId: 9204,
  cycle: { id: 31, name: { ar: 'د', en: 'C' }, status: 'committee-assessment' },
  entity: {
    visibility: 'code_only',
    code: 'C-1A2B3C4D',
    region: { id: 1, code: 'riyadh', name: { ar: 'الرياض', en: 'Riyadh' } },
  },
  status: 'in-progress',
  membershipStatus: 'active',
  isSubstitute: false,
  substituteApprovedAt: null,
  dueAt: '2026-11-22T20:59:00Z',
  isOverdue: true,
  percent: null,
  startedAt: null,
  submittedAt: null,
  returnedAt: null,
}

beforeEach(() => vi.clearAllMocks())

describe('committee roster service', () => {
  it('reads the roster and candidates and maps the members', async () => {
    apiClient.get.mockResolvedValueOnce(answer([member]))
    const { data } = await committeeService.listMembers(31)
    expect(apiClient.get).toHaveBeenCalledWith('/award/cycles/31/committee')
    expect(data[0]).toMatchObject({
      id: 7001,
      status: 'disabled',
      replacedById: 7010,
      progress: { completed: 2 },
    })
    apiClient.get.mockResolvedValueOnce(answer([{ id: 29, name: 'منال' }]))
    await committeeService.listCandidates(31, { search: 'منال' })
    expect(apiClient.get).toHaveBeenLastCalledWith('/award/cycles/31/committee/candidates', {
      params: { search: 'منال' },
    })
  })

  it('posts every roster verb on the seat id and sends the removal reason as a body', async () => {
    apiClient.post.mockResolvedValue(
      answer(member, { affectedRequestIds: [9204], recomputedRequestIds: [] }),
    )
    apiClient.delete.mockResolvedValue({ data: null })
    await committeeService.appoint(31, { userId: 27 })
    expect(apiClient.post).toHaveBeenLastCalledWith('/award/cycles/31/committee/members', {
      userId: 27,
    })
    await committeeService.disable(31, 7001, { reason: 'إجازة طويلة للمقيّم' })
    expect(apiClient.post).toHaveBeenLastCalledWith(
      '/award/cycles/31/committee/members/7001/disable',
      { reason: 'إجازة طويلة للمقيّم' },
    )
    const excluded = await committeeService.exclude(31, 7001)
    expect(excluded.meta).toEqual({ affectedRequestIds: [9204], recomputedRequestIds: [] })
    await committeeService.substitute(31, 7001, { substituteUserId: 31 })
    expect(apiClient.post).toHaveBeenLastCalledWith(
      '/award/cycles/31/committee/members/7001/substitute',
      { substituteUserId: 31 },
    )
    await committeeService.remove(31, 7001, { reason: 'تعيين خاطئ في الدورة' })
    expect(apiClient.delete).toHaveBeenCalledWith('/award/cycles/31/committee/members/7001', {
      data: { reason: 'تعيين خاطئ في الدورة' },
    })
  })

  it('starts the committee and announces through the cycle transitions', async () => {
    apiClient.post.mockResolvedValue(answer({ id: 31, status: 'committee-assessment' }))
    await awardCycleService.startCommittee(31)
    expect(apiClient.post).toHaveBeenLastCalledWith('/award/cycles/31/start-committee', {})
    await awardCycleService.announce(31, { reason: 'إعلان معتمد من الإدارة' })
    expect(apiClient.post).toHaveBeenLastCalledWith('/award/cycles/31/announce', {
      reason: 'إعلان معتمد من الإدارة',
    })
  })
})

describe('committee work service', () => {
  it('maps the assignment rows with the anonymous entity', async () => {
    apiClient.get.mockResolvedValue(answer([row], { pagination: { page: 1 } }))
    const { data, meta } = await committeeWorkService.listAssignments({
      'filter[status]': 'in-progress',
    })
    expect(data[0].entity).toEqual({
      visibility: 'code_only',
      code: 'C-1A2B3C4D',
      name: null,
      region: { id: 1, code: 'riyadh', name: { ar: 'الرياض', en: 'Riyadh' } },
    })
    expect(data[0].isOverdue).toBe(true)
    expect(meta.pagination.page).toBe(1)
  })

  it('maps the evaluation with notes, prior work and the return note', async () => {
    apiClient.get.mockResolvedValue(
      answer({
        assignment: row,
        mode: 'edit',
        assignmentId: 7101,
        status: 'in-progress',
        scale: { kind: 'of_10', maximum: '10.00' },
        scores: { subs: { 12: { rawValue: '8.00', percent: '80.00' } }, points: {} },
        progress: { scored: 1, required: 20 },
        percent: null,
        criteria: { mains: [] },
        cv: { 12: 'وصف' },
        evidence: [],
        notes: { subs: { 12: 'ملاحظة' }, general: null },
        returnNote: { note: 'أعيدوا التقييم', returnedAt: '2026-10-31T08:00:00Z' },
        prior: {
          scores: { subs: {}, points: {} },
          progress: { scored: 0, required: 20 },
          percent: null,
          status: 'completed',
          notes: { subs: {}, general: 'سابقة' },
          viewOnly: true,
        },
      }),
    )
    const { data } = await committeeWorkService.getAssignment(7101)
    expect(data).toMatchObject({
      mode: 'edit',
      scale: { kind: 'of-10', maximum: '10.00' },
      notes: { subs: { 12: 'ملاحظة' }, general: '' },
      returnNote: { note: 'أعيدوا التقييم' },
    })
    expect(data.prior.notes.general).toBe('سابقة')
    expect(data.scores.subs['12']).toEqual({ rawValue: '8.00', percent: '80.00' })
  })

  it('writes scores, notes, submissions and discussion entries on the own routes', async () => {
    apiClient.put.mockResolvedValue(answer({ assignmentId: 7101, subs: {}, general: 'عامة' }))
    apiClient.post.mockResolvedValue(
      answer({
        id: 7101,
        status: 'completed',
        percent: '82.50',
        submittedAt: '2026-11-01T08:00:00Z',
      }),
    )
    await committeeWorkService.saveNotes(7101, { notes: { 12: null }, generalNote: 'عامة' })
    expect(apiClient.put).toHaveBeenLastCalledWith('/committee/me/assignments/7101/notes', {
      notes: { 12: null },
      generalNote: 'عامة',
    })
    const submitted = await committeeWorkService.submit(7101)
    expect(submitted.data.percent).toBe('82.50')
    await committeeWorkService.respond(55, { kind: 'request_edit', body: 'يرجى توضيح أسس المتوسط' })
    expect(apiClient.post).toHaveBeenLastCalledWith('/committee/me/discussions/55/entries', {
      kind: 'request_edit',
      body: 'يرجى توضيح أسس المتوسط',
    })
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'committeeWork.respond',
      expect.objectContaining({ payloadShape: ['kind', 'body'] }),
    )
  })
})

describe('secretary and results services', () => {
  it('maps the desk counts from the wire status keys', async () => {
    apiClient.get.mockResolvedValue(
      answer([], {
        counts: { awaitingSecretaryApproval: 2, underCommitteeAssessment: 1 },
        pagination: { page: 1 },
      }),
    )
    const { meta } = await secretaryService.list(31, {
      'filter[status]': 'awaiting-secretary-approval',
    })
    expect(meta.counts['awaiting-secretary-approval']).toBe(2)
    expect(meta.counts['under-committee-assessment']).toBe(1)
    expect(meta.counts.approved).toBe(0)
  })

  it('posts the secretary decisions on the request routes', async () => {
    apiClient.post.mockResolvedValue(
      answer(
        {
          id: 9205,
          status: 'approved',
          evaluations: [],
          decisions: [],
          discussions: [],
          pendingSubstitutionIds: [],
        },
        { recomputed: true },
      ),
    )
    const recomputed = await secretaryService.recomputeAverage(31, 9205)
    expect(apiClient.post).toHaveBeenLastCalledWith(
      '/award/cycles/31/secretary/requests/9205/average',
      {},
    )
    expect(recomputed.meta.recomputed).toBe(true)
    await secretaryService.returnToCommittee(31, 9205, { generalNote: 'ملاحظة للجنة' })
    expect(apiClient.post).toHaveBeenLastCalledWith(
      '/award/cycles/31/secretary/requests/9205/return',
      { generalNote: 'ملاحظة للجنة' },
    )
    await secretaryService.approveSubstitution(31, 9205, 7150)
    expect(apiClient.post).toHaveBeenLastCalledWith(
      '/award/cycles/31/secretary/requests/9205/substitutions/7150/approve',
      {},
    )
    await secretaryService.closeDiscussion(31, 9205, 44, { summary: 'خلاصة' })
    expect(apiClient.post).toHaveBeenLastCalledWith(
      '/award/cycles/31/secretary/requests/9205/discussions/44/close',
      { summary: 'خلاصة' },
    )
  })

  it('reads the boards and writes ranks and approvals', async () => {
    apiClient.get.mockResolvedValue(
      answer(
        [
          {
            assessmentId: 9204,
            number: 'ADV-2026-000004',
            status: 'approved',
            averagePercent: '84.20',
            regionalRank: null,
            nationalRank: null,
          },
        ],
        {
          cycleStatus: 'awaiting-secretary',
          regions: [{ id: 1, code: 'riyadh', approved: false, total: 1, ranked: 0, pending: 0 }],
          national: { approved: false },
        },
      ),
    )
    const board = await awardResultsService.listRegional(31)
    expect(board.data[0].averagePercent).toBe('84.20')
    expect(board.meta.regions[0]).toMatchObject({ id: 1, approved: false, pending: 0 })
    expect(board.meta.national.announcedAt).toBeNull()
    apiClient.put.mockResolvedValue(answer({ assessmentId: 9204, regionalRank: 1 }))
    await awardResultsService.setRegionalRank(31, 9204, { rank: 1 })
    expect(apiClient.put).toHaveBeenLastCalledWith('/award/cycles/31/results/9204/regional-rank', {
      rank: 1,
    })
    await awardResultsService.setNationalRank(31, 9204, { rank: null })
    expect(apiClient.put).toHaveBeenLastCalledWith('/award/cycles/31/national/9204/rank', {
      rank: null,
    })
    apiClient.post.mockResolvedValue(answer([], {}))
    await awardResultsService.unapproveRegion(31, 2, { reason: 'سبب سحب الاعتماد المطول' })
    expect(apiClient.post).toHaveBeenLastCalledWith('/award/cycles/31/regions/2/unapprove', {
      reason: 'سبب سحب الاعتماد المطول',
    })
  })
})

describe('entity result and criteria publication', () => {
  it('reads the advanced result from the shared result route with the award block', async () => {
    apiClient.get.mockResolvedValue(
      answer({
        id: 9204,
        number: 'ADV-2026-000004',
        path: 'advanced',
        status: 'national-winner',
        available: true,
        percent: '84.20',
        feedback: [],
        award: {
          status: 'national-winner',
          region: { id: 1, code: 'riyadh', name: { ar: 'الرياض', en: 'Riyadh' } },
          regionalRank: 1,
          nationalRank: 1,
        },
      }),
    )
    const { data } = await assessmentsService.getAdvancedResult(9204)
    expect(apiClient.get).toHaveBeenCalledWith('/assessments/9204/result')
    expect(data.award).toMatchObject({ status: 'national-winner', nationalRank: 1 })
  })

  it('sends the draft cycle choice only when given', async () => {
    apiClient.post.mockResolvedValue(answer({ id: 4 }))
    await criteriaService.publishVersion(4, { scope: 'new_only', draftCycles: 'move' })
    expect(apiClient.post).toHaveBeenLastCalledWith('/criteria/versions/4/publish', {
      confirm: true,
      scope: 'new_only',
      reason: null,
      draftCycles: 'move',
    })
    await criteriaService.publishVersion(4, { scope: 'all' })
    expect(apiClient.post).toHaveBeenLastCalledWith('/criteria/versions/4/publish', {
      confirm: true,
      scope: 'all',
      reason: null,
    })
  })
})
