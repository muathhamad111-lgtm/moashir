import { describe, expect, it, vi, beforeEach } from 'vitest'
import { committeeService } from '@/services/committeeService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const envelope = { data: {}, meta: {}, errors: null }
const CYCLE = 'cycle-committee-2026'

beforeEach(() => {
  vi.clearAllMocks()
})

describe('committeeService — committee management (Note 11)', () => {
  it('loads the manageable roster', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    const result = await committeeService.getRoster(CYCLE)
    expect(apiClient.get).toHaveBeenCalledWith(`/award/committee/${CYCLE}/roster`)
    expect(result).toEqual(envelope)
  })

  it('adds a committee assessor and logs only the payload shape', async () => {
    apiClient.post.mockResolvedValue({ data: envelope })
    await committeeService.addCommitteeAssessor(CYCLE, { committeeAssessorId: 'jur-9501' })
    expect(apiClient.post).toHaveBeenCalledWith(
      `/award/committee/${CYCLE}/roster/committee-assessors`,
      {
        committeeAssessorId: 'jur-9501',
      },
    )
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'committee.addCommitteeAssessor',
      expect.objectContaining({ payloadShape: ['committeeAssessorId'] }),
    )
  })

  it('removes a committee assessor via DELETE', async () => {
    apiClient.delete.mockResolvedValue({ data: envelope })
    await committeeService.removeCommitteeAssessor(CYCLE, 'jur-9401')
    expect(apiClient.delete).toHaveBeenCalledWith(
      `/award/committee/${CYCLE}/roster/committee-assessors/jur-9401`,
    )
  })

  it('reassigns one assessment (logs the shape only)', async () => {
    apiClient.post.mockResolvedValue({ data: envelope })
    await committeeService.reassignAssessment(CYCLE, {
      fromCommitteeAssessorId: 'jur-9403',
      toCommitteeAssessorId: 'jur-9404',
      assessmentId: 9402,
    })
    expect(apiClient.post).toHaveBeenCalledWith(`/award/committee/${CYCLE}/roster/reassign`, {
      fromCommitteeAssessorId: 'jur-9403',
      toCommitteeAssessorId: 'jur-9404',
      assessmentId: 9402,
    })
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'committee.reassignAssessment',
      expect.objectContaining({
        payloadShape: ['fromCommitteeAssessorId', 'toCommitteeAssessorId', 'assessmentId'],
      }),
    )
  })

  it('redistributes assignments', async () => {
    apiClient.post.mockResolvedValue({ data: envelope })
    await committeeService.redistribute(CYCLE)
    expect(apiClient.post).toHaveBeenCalledWith(`/award/committee/${CYCLE}/roster/redistribute`)
  })

  it('reports failures to Sentry and rethrows (never swallows)', async () => {
    const failure = new Error('boom')
    apiClient.post.mockRejectedValue(failure)
    await expect(
      committeeService.addCommitteeAssessor(CYCLE, { committeeAssessorId: 'x' }),
    ).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'committee.addCommitteeAssessor' }),
    )
  })
})

describe('committeeService — secretary review-all (Note 13)', () => {
  it('lists the review entities', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await committeeService.listReviewEntities(CYCLE)
    expect(apiClient.get).toHaveBeenCalledWith(`/award/committee/${CYCLE}/secretary/entities`, {
      params: {},
    })
  })

  it('lists all committee assessors for one entity', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await committeeService.listEntityCommitteeAssessors(CYCLE, 9401)
    expect(apiClient.get).toHaveBeenCalledWith(
      `/award/committee/${CYCLE}/secretary/9401/committee-assessors`,
    )
  })

  it('fetches ONE committee assessor detail (read-only) for an entity', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await committeeService.getEntityCommitteeAssessorDetail(CYCLE, 9401, 'jur-9401')
    expect(apiClient.get).toHaveBeenCalledWith(
      `/award/committee/${CYCLE}/secretary/9401/committee-assessors/jur-9401/detail`,
    )
  })
})
