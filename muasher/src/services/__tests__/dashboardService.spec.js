import { describe, expect, it, vi, beforeEach } from 'vitest'
import { dashboardService } from '@/services/dashboardService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const ACTION_ITEM = {
  key: 'submit_accredited_draft',
  count: 1,
  severity: 'warning',
  link: '/assessment/accredited',
  dueAt: null,
  context: {
    assessmentId: 14,
    paymentId: null,
    messageId: null,
    reference: 'ACC-2026-000014',
    remainingSubs: 4,
    percent: null,
  },
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('dashboardService', () => {
  it('requests a staff board with ?board= and maps the generic summary', async () => {
    apiClient.get.mockResolvedValue({
      data: {
        data: {
          board: 'request_follower',
          landingBoard: 'request_follower',
          availableBoards: ['request_follower', 'assessor'],
          cards: [
            { key: 'awaiting_assignment', value: 3, format: 'count', link: '/admin/requests' },
            {},
          ],
          widgets: [
            { key: 'pipeline', data: [{ path: 'accredited', status: 'submitted', count: 3 }] },
          ],
          actionCenter: [{ key: 'assign_requests', count: 3, severity: 'warning', link: '/x' }],
          generatedAt: '2026-11-05T06:00:00+00:00',
        },
      },
    })
    const { data } = await dashboardService.getBoard('request_follower')
    expect(apiClient.get).toHaveBeenCalledWith('/dashboard/summary', {
      params: { board: 'request_follower' },
    })
    expect(data.availableBoards).toEqual(['request_follower', 'assessor'])
    expect(data.cards).toEqual([
      { key: 'awaiting_assignment', value: 3, format: 'count', link: '/admin/requests' },
    ])
    expect(data.widgets[0].key).toBe('pipeline')
    expect(data.actionCenter[0]).toMatchObject({ key: 'assign_requests', context: null })
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'dashboard.getSummary',
      expect.objectContaining({ entity: 'dashboard', paramsShape: ['board'] }),
    )
  })

  it('asks for the landing board without a board parameter', async () => {
    apiClient.get.mockResolvedValue({ data: { data: { board: 'system_admin' } } })
    await dashboardService.getBoard()
    expect(apiClient.get).toHaveBeenCalledWith('/dashboard/summary', { params: {} })
  })

  it('treats a refused board as an expected answer, not a reported error', async () => {
    const refusal = Object.assign(new Error('refused'), { response: { status: 403 } })
    apiClient.get.mockRejectedValue(refusal)
    await expect(dashboardService.getBoard('finance_officer')).rejects.toBe(refusal)
    expect(logger.error).not.toHaveBeenCalled()
  })

  it('reads the entity board widgets and its Action Center', async () => {
    apiClient.get.mockResolvedValue({
      data: {
        data: {
          board: 'entity',
          widgets: [
            {
              key: 'latest_result',
              data: { assessmentId: 9, path: 'self', percent: '78.00', completedAt: 'x' },
            },
            { key: 'payments_card', data: { receiptsCount: 1 } },
          ],
          cards: [{ key: 'accepted_evidence_count', value: 7, format: 'count', link: null }],
          actionCenter: [ACTION_ITEM, { ...ACTION_ITEM, key: 'complete_payment', count: 0 }],
        },
      },
    })
    const { data } = await dashboardService.getEntityBoard()
    expect(apiClient.get).toHaveBeenCalledWith('/dashboard/summary', {
      params: { board: 'entity' },
    })
    expect(data.latestResult).toMatchObject({ assessmentId: 9, percent: '78.00' })
    expect(data.paymentsCard).toEqual({ receiptsCount: 1 })
    expect(data.acceptedEvidenceCount).toBe(7)
    expect(data.actionCenter).toEqual([ACTION_ITEM])
  })

  it('keeps the evidence count null when the board has no such card', async () => {
    apiClient.get.mockResolvedValue({ data: { data: { board: 'entity', widgets: [] } } })
    const { data } = await dashboardService.getEntityBoard()
    expect(data.acceptedEvidenceCount).toBeNull()
  })

  it('loads the Action Center and keeps the context', async () => {
    apiClient.get.mockResolvedValue({ data: { data: [ACTION_ITEM], meta: { total: 1 } } })
    const { data, meta } = await dashboardService.getActionCenter()
    expect(apiClient.get).toHaveBeenCalledWith('/dashboard/action-center')
    expect(data).toEqual([ACTION_ITEM])
    expect(meta.total).toBe(1)
  })

  it('exposes the server time of the Action Center data', async () => {
    apiClient.get.mockResolvedValue({
      data: { data: [], meta: { total: 0, generatedAt: '2026-11-05T08:00:00+00:00' } },
    })
    const { generatedAt } = await dashboardService.getActionCenter()
    expect(generatedAt).toBe('2026-11-05T08:00:00+00:00')
  })

  it('reports failures to Sentry and rethrows (never swallows)', async () => {
    const failure = new Error('boom')
    apiClient.get.mockRejectedValue(failure)
    await expect(dashboardService.getBoard('request_follower')).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'dashboard.getSummary' }),
    )
  })
})
