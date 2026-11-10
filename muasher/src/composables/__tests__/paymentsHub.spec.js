import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { withSetup } from '@/test-utils/withSetup'
import { usePaymentsHub } from '@/composables/usePaymentsHub'
import { usePaymentCompletion, COMPLETION_STATES } from '@/composables/usePaymentCompletion'
import { paymentsService } from '@/services/paymentsService'

vi.mock('@/services/paymentsService', () => ({
  paymentsService: { listMine: vi.fn(), get: vi.fn(), create: vi.fn() },
}))

const FUTURE = new Date(Date.now() + 18 * 60 * 1000).toISOString()
const PAST = new Date(Date.now() - 60 * 1000).toISOString()
const PRICING = {
  priceBeforeVat: '1000.00',
  vatAmount: '150.00',
  total: '1150.00',
  currency: 'SAR',
}

function payment(id, status, extra = {}) {
  return {
    id,
    reference: `PAY-2026-0000${id}`,
    purpose: 'accredited',
    status,
    pricing: PRICING,
    ...extra,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
  paymentsService.listMine.mockImplementation((params) => {
    if (params.perPage === 1)
      return Promise.resolve({
        data: [],
        meta: {
          stats: {
            count: 2,
            paidCount: 1,
            totalPaid: '1150.00',
            totalVat: '150.00',
            currency: 'SAR',
          },
          statusCounts: { created: 1, pending: 0, paid: 1, failed: 1, cancelled: 1, refunded: 0 },
        },
      })
    if (params['filter[status]'] === 'created')
      return Promise.resolve({
        data: [
          payment(21, 'created', { expiresAt: FUTURE }),
          payment(22, 'created', { expiresAt: PAST }),
        ],
        meta: {},
      })
    return Promise.resolve({
      data: [payment(21, 'created', { expiresAt: FUTURE }), payment(23, 'paid', { isTest: true })],
      meta: { pagination: { lastPage: 1 } },
    })
  })
})

describe('usePaymentsHub', () => {
  it('groups the tab counts and keeps the stats of the unfiltered call', async () => {
    const { api } = withSetup(() => usePaymentsHub())
    await flushPromises()
    expect(api.tabs.value.map((tab) => [tab.value, tab.count])).toEqual([
      ['all', 4],
      ['awaiting', 1],
      ['paid', 1],
      ['cancelled', 2],
    ])
    expect(api.stats.value.totalPaid).toBe('1150.00')
    expect(api.hasTestRows.value).toBe(true)
  })

  it('shows only unexpired open orders as cards and keeps them out of the table', async () => {
    const { api } = withSetup(() => usePaymentsHub())
    await flushPromises()
    expect(api.pendingOrders.value.map((order) => order.id)).toEqual([21])
    expect(api.tableRows.value.map((row) => row.id)).toEqual([23])
  })

  it('asks the tab statuses as one comma list', async () => {
    const { api } = withSetup(() => usePaymentsHub())
    await flushPromises()
    api.tab.value = 'cancelled'
    await vi.waitFor(() =>
      expect(paymentsService.listMine).toHaveBeenLastCalledWith(
        expect.objectContaining({ 'filter[status]': 'cancelled,failed,rejected' }),
      ),
    )
  })
})

describe('usePaymentCompletion', () => {
  it('reopens the open order with a fresh key and reports a replaced order id', async () => {
    paymentsService.get.mockResolvedValue({ data: payment(21, 'created', { expiresAt: FUTURE }) })
    paymentsService.create.mockResolvedValue({
      data: { ...payment(24, 'created', { expiresAt: FUTURE }), checkout: { expiresAt: FUTURE } },
      meta: { existingOrder: false },
    })
    const onReplaced = vi.fn()
    const { api } = withSetup(() => usePaymentCompletion('21', { onReplaced }))
    await flushPromises()
    expect(paymentsService.create).toHaveBeenCalledWith(
      { purpose: 'accredited', awardCycleId: null },
      expect.any(String),
    )
    expect(api.state.value).toBe(COMPLETION_STATES.CHECKOUT)
    expect(api.isReady.value).toBe(true)
    expect(onReplaced).toHaveBeenCalledWith(24)
  })

  it('does not start a checkout for a paid order and reports a missing one', async () => {
    paymentsService.get.mockResolvedValueOnce({ data: payment(23, 'paid') })
    const paid = withSetup(() => usePaymentCompletion('23'))
    await flushPromises()
    expect(paid.api.state.value).toBe(COMPLETION_STATES.CLOSED)
    expect(paymentsService.create).not.toHaveBeenCalled()
    paymentsService.get.mockRejectedValueOnce(
      Object.assign(new Error('404'), { normalized: { status: 404 } }),
    )
    const missing = withSetup(() => usePaymentCompletion('99'))
    await flushPromises()
    expect(missing.api.state.value).toBe(COMPLETION_STATES.MISSING)
  })
})
