import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { computed } from 'vue'
import { withSetup } from '@/test-utils/withSetup'
import { useRequestQueue } from '@/composables/useRequestQueue'
import { requestsService } from '@/services/requestsService'

vi.mock('@/services/requestsService', () => ({
  requestsService: { listQueue: vi.fn(), listAssigned: vi.fn() },
}))

const COUNTS = [
  { path: 'accredited', pathLabel: null, total: 3, awaitingAssignment: 1, overdue: 0 },
  { path: 'advanced', pathLabel: null, total: 2, awaitingAssignment: 1, overdue: 1 },
]

function lastParams() {
  return requestsService.listQueue.mock.calls.at(-1)[0]
}

beforeEach(() => {
  requestsService.listQueue.mockResolvedValue({
    data: [],
    meta: { pagination: { page: 1, lastPage: 1 }, stats: null, pathCounts: COUNTS },
  })
})

async function mountAt(path) {
  const setup = withSetup(() => useRequestQueue(computed(() => ['accredited', 'advanced'])))
  await setup.globals.router.push(path)
  await flushPromises()
  return setup
}

describe('useRequestQueue', () => {
  it('serves the path of the link with the status of that path', async () => {
    const { api, globals } = await mountAt('/admin/requests')
    expect(lastParams()).toMatchObject({ 'filter[path]': 'accredited' })
    await globals.router.push('/admin/requests?status=submitted,awaiting_review_assignment')
    await flushPromises()
    expect(lastParams()).toMatchObject({
      'filter[path]': 'accredited',
      'filter[status]': 'submitted',
    })
    await globals.router.push(
      '/admin/requests?status=submitted,awaiting_review_assignment&path=advanced',
    )
    await flushPromises()
    expect(lastParams()).toMatchObject({
      'filter[path]': 'advanced',
      'filter[status]': 'awaiting-review-assignment',
    })
    expect(api.linkQuery.value).toEqual({ status: 'submitted,awaiting_review_assignment' })
  })

  it('applies the overdue link and drops the link status once the user picks a status', async () => {
    const { api, globals } = await mountAt('/admin/requests?overdue=true&status=submitted')
    await globals.router.push('/admin/requests?overdue=true&status=submitted&path=accredited')
    await flushPromises()
    expect(lastParams()).toMatchObject({ 'filter[overdue]': 'true' })
    api.chooseFilter('status', 'completed')
    await flushPromises()
    expect(globals.router.currentRoute.value.query.status).toBeUndefined()
    expect(lastParams()).toMatchObject({ 'filter[status]': 'completed' })
    expect(api.pathCounts.value).toEqual(COUNTS)
  })
})
