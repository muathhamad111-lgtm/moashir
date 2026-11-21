import { describe, expect, it } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { withSetup } from '@/test-utils/withSetup'
import { useQueueStage } from '@/composables/useQueueStage'
import { toQueueListRow, toQueueStats, toRequestDetail } from '@/services/mappers/requests'
import { toLinkedRequest } from '@/services/mappers/bankTransfers'

describe('useQueueStage', () => {
  it('reads and writes the list stage in the address and falls back to all', async () => {
    const { api, globals } = withSetup(() => useQueueStage())
    await globals.router.push('/admin/requests?path=accredited&stage=preparing')
    expect(api.stage.value).toBe('preparing')
    api.stage.value = 'all'
    await flushPromises()
    expect(globals.router.currentRoute.value.query).toEqual({ path: 'accredited' })
    expect(api.stage.value).toBe('all')
    await globals.router.push('/admin/requests?stage=archived')
    expect(api.stage.value).toBe('all')
  })
})

describe('paid request mappers', () => {
  it('maps a draft row with the entity progress and a reassigned queue row', () => {
    const draft = toQueueListRow({
      id: 9101,
      number: 'ACC-2026-000004',
      status: 'draft',
      openedAt: '2026-09-03T07:26:00Z',
      progress: { subsComplete: 3, subsTotal: 19 },
      lastActivityAt: '2026-09-03T07:40:00Z',
    })
    expect(draft).toMatchObject({
      progress: { complete: 3, total: 19 },
      openedAt: '2026-09-03T07:26:00Z',
      assessor: null,
      isReassigned: false,
    })
    const moved = toQueueListRow({ id: 1, assessor: { id: 11, name: 'هند' }, isReassigned: true })
    expect(moved).toMatchObject({
      assessor: { id: 11, name: 'هند' },
      isReassigned: true,
      progress: null,
    })
  })

  it('maps the stage counts and fills the missing ones with zero', () => {
    const stats = toQueueStats({ total: 4, byStage: { preparing: 1, closed: 2 }, overdue: 1 })
    expect(stats.byStage).toEqual({
      preparing: 1,
      awaiting_assignment: 0,
      in_progress: 0,
      overdue: 0,
      closed: 2,
    })
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
