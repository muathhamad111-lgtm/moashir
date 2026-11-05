import { beforeEach, describe, expect, it, vi } from 'vitest'
import { financeService } from '@/services/financeService'
import { financeSettingsService } from '@/services/financeSettingsService'
import { filesService, UntrustedDownloadLink } from '@/services/filesService'
import { apiClient } from '@/api/client'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const FILE_ID = '9a4e6b21-0c3d-4f5a-8e7b-1d2c00000001'

beforeEach(() => {
  vi.clearAllMocks()
})

describe('financeService', () => {
  it('returns a streamed CSV export as a file with its row count', async () => {
    apiClient.post.mockResolvedValue({
      status: 200,
      data: new Blob(['reference\n']),
      headers: {
        'content-disposition': 'attachment; filename="transactions-20261101-101500.csv"',
        'x-export-row-count': '7',
      },
    })
    const result = await financeService.exportTransactions({ 'filter[status]': 'paid' })
    expect(apiClient.post).toHaveBeenCalledWith('/finance/transactions/export', null, {
      params: { 'filter[status]': 'paid' },
      responseType: 'blob',
    })
    expect(result).toMatchObject({
      kind: 'file',
      fileName: 'transactions-20261101-101500.csv',
      rowCount: 7,
    })
  })

  it('decodes a queued export answer from the blob body', async () => {
    const body = {
      data: {
        id: 'e-1',
        status: 'queued',
        row_count: 6200,
        created_at: '2026-11-01T10:00:00Z',
        completed_at: null,
      },
      meta: {},
      errors: null,
    }
    apiClient.post.mockResolvedValue({
      status: 202,
      data: new Blob([JSON.stringify(body)]),
      headers: {},
    })
    const result = await financeService.exportTransactions({})
    expect(result).toEqual({
      kind: 'queued',
      export: {
        id: 'e-1',
        status: 'queued',
        rowCount: 6200,
        createdAt: '2026-11-01T10:00:00Z',
        completedAt: null,
      },
    })
  })

  it('records a refund as multipart without logging any value', async () => {
    apiClient.post.mockResolvedValue({ data: { data: {} } })
    const form = new FormData()
    form.append('amount', '1150.00')
    form.append('evidence', new Blob(['%PDF']), 'transfer.pdf')
    await financeService.recordRefund('p-1', form)
    expect(apiClient.post).toHaveBeenCalledWith('/finance/payments/p-1/refunds', form)
  })

  it('reaches every staff endpoint of the contract', async () => {
    apiClient.get.mockResolvedValue({ data: { data: {} } })
    apiClient.post.mockResolvedValue({ data: { data: {} } })
    await financeService.getSummary({ from: '2026-10-01' })
    await financeService.listTransactions({ page: 1 })
    await financeService.getTransaction('p-1')
    await financeService.getExport('e-1')
    await financeService.listRefunds({})
    await financeService.listPaymentEvents({})
    await financeService.requestExportDownload('e-1')
    await financeService.requestEvidenceDownload('r-1')
    await financeService.requestReceiptDownload('c-1')
    await financeService.reissueReceipt('c-1', 'The entity asked for a corrected copy.')
    await financeService.manualSettle('p-1', {
      status: 'paid',
      reason: 'Confirmed in the gateway dashboard today.',
    })
    expect(apiClient.get.mock.calls.map((call) => call[0])).toEqual([
      '/finance/summary',
      '/finance/transactions',
      '/finance/transactions/p-1',
      '/finance/exports/e-1',
      '/finance/refunds',
      '/finance/payment-events',
    ])
    expect(apiClient.post.mock.calls.map((call) => call[0])).toEqual([
      '/finance/exports/e-1/download',
      '/finance/refunds/r-1/evidence/download',
      '/finance/receipts/c-1/download',
      '/finance/receipts/c-1/reissue',
      '/finance/payments/p-1/manual-settle',
    ])
  })

  it('reads and partially updates the finance settings', async () => {
    apiClient.get.mockResolvedValue({ data: { data: {} } })
    apiClient.put.mockResolvedValue({ data: { data: {} } })
    await financeSettingsService.get()
    await financeSettingsService.update({ vatRate: '5.00' })
    expect(apiClient.get).toHaveBeenCalledWith('/settings/finance')
    expect(apiClient.put).toHaveBeenCalledWith('/settings/finance', { vatRate: '5.00' })
  })
})

describe('filesService', () => {
  it('follows a same-origin signed link through the API client, keeping its query untouched', async () => {
    apiClient.get.mockResolvedValue({
      data: new Blob(['%PDF']),
      headers: { 'content-disposition': 'attachment; filename="RCP-2026-000001.pdf"' },
    })
    const url = `${window.location.origin}/api/v1/files/${FILE_ID}/download?expires=1&signature=abc&user=22`
    const result = await filesService.fetchSignedFile({ url, fileName: 'fallback.pdf' })
    expect(apiClient.get).toHaveBeenCalledWith(
      `/files/${FILE_ID}/download?expires=1&signature=abc&user=22`,
      { responseType: 'blob' },
    )
    expect(result.fileName).toBe('RCP-2026-000001.pdf')
  })

  it('refuses a link to another origin or another path', async () => {
    await expect(
      filesService.fetchSignedFile({
        url: `https://elsewhere.example.sa/api/v1/files/${FILE_ID}/download`,
      }),
    ).rejects.toBeInstanceOf(UntrustedDownloadLink)
    await expect(
      filesService.fetchSignedFile({ url: `${window.location.origin}/api/v1/users/22` }),
    ).rejects.toBeInstanceOf(UntrustedDownloadLink)
    expect(apiClient.get).not.toHaveBeenCalled()
  })
})
