import { describe, expect, it } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { withSetup } from '@/test-utils/withSetup'
import { useQueueStage } from '@/composables/useQueueStage'
import { toPreparingRow, toRequestDetail } from '@/services/mappers/requests'
import { toLinkedRequest } from '@/services/mappers/bankTransfers'

describe('useQueueStage', () => {
  it('reads and writes the paid drafts tab in the address', async () => {
    const { api, globals } = withSetup(() => useQueueStage())
    await globals.router.push('/admin/requests?path=accredited&stage=preparing')
    expect(api.stage.value).toBe('preparing')
    expect(api.stageQuery.value).toEqual({ stage: 'preparing' })
    api.stage.value = 'queue'
    await flushPromises()
    expect(globals.router.currentRoute.value.query).toEqual({ path: 'accredited' })
    expect(api.stage.value).toBe('queue')
  })
})

describe('paid request mappers', () => {
  it('maps a paid draft row and its payment', () => {
    const row = toPreparingRow({
      id: 9101,
      number: 'ACC-2026-000004',
      status: 'draft',
      payment: { reference: 'PAY-2026-000012', status: 'paid', receipt: { number: 'RCP-1' } },
      progress: { subsComplete: 3, subsTotal: 19 },
      lastActivityAt: '2026-09-03T07:40:00Z',
    })
    expect(row.progress).toEqual({ complete: 3, total: 19 })
    expect(row.payment).toMatchObject({ id: null, reference: 'PAY-2026-000012' })
    expect(row.payment.receipt).toMatchObject({ id: null, number: 'RCP-1', isTest: false })
  })

  it('keeps the request detail payment flag and drops empty details', () => {
    expect(toRequestDetail({ payment: { paid: true } }).payment).toEqual({
      paid: true,
      details: null,
    })
  })

  it('maps the linked request and treats a missing one as none', () => {
    expect(toLinkedRequest(null)).toBeNull()
    expect(
      toLinkedRequest({
        number: 'ACC-2026-000009',
        status: 'draft',
        progress: { subsComplete: 3, subsTotal: 19 },
      }),
    ).toMatchObject({ id: null, number: 'ACC-2026-000009', progress: { complete: 3, total: 19 } })
  })
})
