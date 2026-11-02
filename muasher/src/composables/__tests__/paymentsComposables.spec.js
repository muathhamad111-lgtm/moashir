import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { CHECKOUT_PHASES, useCheckout } from '@/composables/useCheckout'
import { RETURN_STATES, usePaymentReturn } from '@/composables/usePaymentReturn'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { useFinanceList } from '@/composables/useFinanceList'
import { paymentsService } from '@/services/paymentsService'
import { filesService } from '@/services/filesService'
import { downloadBlob } from '@/utils/download'
import { withSetup } from '@/test-utils/withSetup'

vi.mock('@/services/paymentsService', () => ({
  paymentsService: { create: vi.fn(), verify: vi.fn(), get: vi.fn() },
}))
vi.mock('@/services/filesService', () => ({
  filesService: { fetchSignedFile: vi.fn() },
}))
vi.mock('@/utils/download', () => ({ downloadBlob: vi.fn() }))

const PAYMENT = { id: 'p-1', reference: 'PAY-2026-000021', purpose: 'accredited', status: 'created', isTest: true, pricing: {} }

function checkoutAnswer({ status = 'created', expiresAt = new Date(Date.now() + 30 * 60000).toISOString(), checkout = true } = {}) {
  return {
    data: { ...PAYMENT, status, checkout: checkout ? { mode: 'test', expiresAt } : null },
    meta: { idempotentReplay: false, existingOrder: false },
  }
}

function answered(status, code) {
  return Object.assign(new Error(code), { response: { status }, normalized: { status, code } })
}

beforeEach(() => {
  vi.clearAllMocks()
})

afterEach(() => {
  vi.useRealTimers()
})

describe('useCheckout', () => {
  it('opens one order per attempt with a fresh Idempotency-Key and shows the form', async () => {
    paymentsService.create.mockResolvedValue(checkoutAnswer())
    const { api } = withSetup(() => useCheckout('accredited'))
    const pending = api.start()
    expect(api.isStarting.value).toBe(true)
    api.start()
    await pending
    expect(paymentsService.create).toHaveBeenCalledTimes(1)
    expect(api.phase.value).toBe(CHECKOUT_PHASES.READY)
    expect(api.isTest.value).toBe(true)
    await api.start()
    const [first, second] = paymentsService.create.mock.calls.map((call) => call[1])
    expect(first).toMatch(/^[0-9a-f-]{36}$/)
    expect(second).not.toBe(first)
  })

  it('reuses the key after a network failure and renews it after a server answer', async () => {
    paymentsService.create.mockRejectedValueOnce(new Error('offline'))
    paymentsService.create.mockRejectedValueOnce(answered(503, 'provider_unavailable'))
    paymentsService.create.mockResolvedValueOnce(checkoutAnswer())
    const { api } = withSetup(() => useCheckout('bag'))
    await api.start()
    expect(api.phase.value).toBe(CHECKOUT_PHASES.BLOCKED)
    await api.start()
    expect(api.failure.value.code).toBe('provider_unavailable')
    await api.start()
    const keys = paymentsService.create.mock.calls.map((call) => call[1])
    expect(keys[1]).toBe(keys[0])
    expect(keys[2]).not.toBe(keys[1])
    expect(paymentsService.create).toHaveBeenCalledWith('bag', expect.any(String))
  })

  it('reports an order already in progress and expires the open order at its deadline', async () => {
    vi.useFakeTimers()
    paymentsService.create.mockResolvedValueOnce(checkoutAnswer({ status: 'pending', checkout: false }))
    const { api } = withSetup(() => useCheckout('accredited'))
    await api.start()
    expect(api.phase.value).toBe(CHECKOUT_PHASES.IN_PROGRESS)
    paymentsService.create.mockResolvedValueOnce(checkoutAnswer({ expiresAt: new Date(Date.now() + 5000).toISOString() }))
    await api.start()
    expect(api.phase.value).toBe(CHECKOUT_PHASES.READY)
    vi.advanceTimersByTime(6000)
    await flushPromises()
    expect(api.phase.value).toBe(CHECKOUT_PHASES.EXPIRED)
  })
})

describe('usePaymentReturn', () => {
  function verified(status, verification = 'applied') {
    return { data: { ...PAYMENT, status }, meta: { verification } }
  }

  it('verifies with the gateway id only and polls with backoff until the server confirms', async () => {
    vi.useFakeTimers()
    paymentsService.verify.mockResolvedValueOnce(verified('pending', 'pending'))
    paymentsService.verify.mockResolvedValueOnce(verified('paid'))
    const { api } = withSetup(() => usePaymentReturn('p-1', 'mock_pay_1', { delays: [1000, 2000] }))
    await api.start()
    expect(paymentsService.verify).toHaveBeenCalledWith('p-1', 'mock_pay_1')
    expect(api.state.value).toBe(RETURN_STATES.PENDING)
    await vi.advanceTimersByTimeAsync(1000)
    expect(api.state.value).toBe(RETURN_STATES.PAID)
    expect(paymentsService.verify).toHaveBeenCalledTimes(2)
  })

  it('stops polling and promises a notification when the payment stays pending', async () => {
    vi.useFakeTimers()
    paymentsService.verify.mockResolvedValue(verified('pending', 'pending'))
    const { api } = withSetup(() => usePaymentReturn('p-1', 'mock_pay_1', { delays: [1000, 2000] }))
    await api.start()
    await vi.advanceTimersByTimeAsync(3000)
    expect(api.state.value).toBe(RETURN_STATES.NOTIFY)
    expect(paymentsService.verify).toHaveBeenCalledTimes(3)
  })

  it('maps a late payment, a failure and a missing payment', async () => {
    paymentsService.verify.mockResolvedValueOnce(verified('cancelled', 'late_payment'))
    const late = withSetup(() => usePaymentReturn('p-1', 'mock_pay_1')).api
    await late.start()
    expect(late.state.value).toBe(RETURN_STATES.LATE)
    paymentsService.verify.mockResolvedValueOnce(verified('failed'))
    const failed = withSetup(() => usePaymentReturn('p-1', 'mock_pay_1')).api
    await failed.start()
    expect(failed.state.value).toBe(RETURN_STATES.FAILED)
    paymentsService.verify.mockRejectedValueOnce(answered(404, 'not_found'))
    const missing = withSetup(() => usePaymentReturn('p-2', 'mock_pay_1')).api
    await missing.start()
    expect(missing.state.value).toBe(RETURN_STATES.NOT_FOUND)
  })

  it('reads the payment without verifying when the gateway returned no id', async () => {
    paymentsService.get.mockResolvedValue({ data: { ...PAYMENT, status: 'pending' } })
    const { api } = withSetup(() => usePaymentReturn('p-1', null))
    await api.start()
    expect(paymentsService.verify).not.toHaveBeenCalled()
    expect(api.state.value).toBe(RETURN_STATES.UNCONFIRMED)
  })
})

describe('useSignedDownload', () => {
  it('follows the signed link and saves the file', async () => {
    filesService.fetchSignedFile.mockResolvedValue({ blob: new Blob(['%PDF']), fileName: 'RCP-2026-000001.pdf' })
    const { api } = withSetup(() => useSignedDownload())
    const link = { url: '/api/v1/files/x/download', fileName: 'RCP-2026-000001.pdf' }
    expect(await api.download('receipt', () => Promise.resolve({ data: link }))).toBe(true)
    expect(filesService.fetchSignedFile).toHaveBeenCalledWith(link)
    expect(downloadBlob).toHaveBeenCalledWith(expect.any(Blob), 'RCP-2026-000001.pdf')
  })

  it('flags a receipt that cannot be rendered right now', async () => {
    const { api } = withSetup(() => useSignedDownload())
    const ok = await api.download('receipt', () => Promise.reject(answered(503, 'document.renderer_unavailable')))
    expect(ok).toBe(false)
    expect(api.unavailable.receipt).toBe(true)
    expect(api.busy.receipt).toBe(false)
  })
})

describe('useFinanceList', () => {
  it('debounces the search and sends it only from two characters', async () => {
    vi.useFakeTimers()
    const fetcher = vi.fn().mockResolvedValue({ data: [], meta: {} })
    const { api } = withSetup(() => useFinanceList({ fetcher, initialFilters: { status: '' } }))
    await flushPromises()
    fetcher.mockClear()
    api.searchInput.value = 'ن'
    await vi.advanceTimersByTimeAsync(400)
    expect(fetcher).not.toHaveBeenCalled()
    api.searchInput.value = 'نماء'
    await vi.advanceTimersByTimeAsync(400)
    expect(fetcher).toHaveBeenCalledTimes(1)
    expect(fetcher.mock.calls[0][0]).toMatchObject({ search: 'نماء', page: 1 })
    api.setFilter('status', 'paid')
    await flushPromises()
    expect(fetcher.mock.calls.at(-1)[0]).toMatchObject({ 'filter[status]': 'paid', search: 'نماء' })
  })
})
