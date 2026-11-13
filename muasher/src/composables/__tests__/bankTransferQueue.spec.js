import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { withSetup } from '@/test-utils/withSetup'
import { useBankTransferQueue } from '@/composables/useBankTransferQueue'
import { bankTransfersService } from '@/services/bankTransfersService'
import { TRANSFER_ACCOUNTS_PER_PAGE } from '@/constants/bankTransfer'

vi.mock('@/services/bankTransfersService', () => ({
  bankTransfersService: { list: vi.fn(), listAccounts: vi.fn() },
}))

const ACCOUNTS = [
  {
    id: 'acc-1',
    bankName: { ar: 'بنك المثال', en: 'Example Bank' },
    ibanLast4: '7519',
    state: 'active',
  },
  {
    id: 'acc-2',
    bankName: { ar: 'مصرف الواحة', en: 'Al-Waha Bank' },
    ibanLast4: '7890',
    state: 'archived',
  },
]

function queuePage(counts) {
  return {
    data: [],
    meta: { pagination: { page: 1, perPage: 15, total: 0, lastPage: 1 }, counts },
  }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useBankTransferQueue', () => {
  it('reads the three stat counts from the queue page and the accounts from their endpoint', async () => {
    bankTransfersService.list.mockResolvedValue(
      queuePage({ underReview: 4, approvedToday: 2, rejectedToday: 1 }),
    )
    bankTransfersService.listAccounts.mockResolvedValue({ data: ACCOUNTS, meta: {} })
    const { api } = withSetup(() => useBankTransferQueue())
    await flushPromises()
    expect(api.counts.value).toEqual({ underReview: 4, approvedToday: 2, rejectedToday: 1 })
    expect(api.underReviewCount.value).toBe(4)
    expect(api.accounts.value).toEqual(ACCOUNTS)
    expect(bankTransfersService.listAccounts).toHaveBeenCalledWith({
      perPage: TRANSFER_ACCOUNTS_PER_PAGE,
    })
    expect(bankTransfersService.list).toHaveBeenCalledTimes(1)
    expect(bankTransfersService.list.mock.calls[0][0]).not.toMatchObject({ perPage: 1 })
  })

  it('refreshes the counts with the list and keeps unknown counts empty', async () => {
    bankTransfersService.list.mockResolvedValueOnce(queuePage({ underReview: 1 }))
    bankTransfersService.listAccounts.mockResolvedValue({ data: [], meta: {} })
    const { api } = withSetup(() => useBankTransferQueue())
    await flushPromises()
    expect(api.counts.value).toEqual({ underReview: 1, approvedToday: null, rejectedToday: null })
    bankTransfersService.list.mockResolvedValueOnce(
      queuePage({ underReview: 0, approvedToday: 1, rejectedToday: 0 }),
    )
    await api.refresh()
    expect(api.counts.value).toEqual({ underReview: 0, approvedToday: 1, rejectedToday: 0 })
    expect(bankTransfersService.listAccounts).toHaveBeenCalledTimes(1)
  })

  it('offers no account choices when the account list cannot be read', async () => {
    bankTransfersService.list.mockResolvedValue(queuePage({ underReview: 0 }))
    bankTransfersService.listAccounts.mockRejectedValue(new Error('offline'))
    const { api } = withSetup(() => useBankTransferQueue())
    await flushPromises()
    expect(api.accounts.value).toEqual([])
  })
})
