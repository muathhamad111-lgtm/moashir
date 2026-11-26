import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { computed } from 'vue'
import { withSetup } from '@/test-utils/withSetup'
import { useRequestQueue } from '@/composables/useRequestQueue'
import { useFilterDraft } from '@/composables/useFilterDraft'
import { useQueueStage } from '@/composables/useQueueStage'
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
    const { globals } = await mountAt('/admin/requests')
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

  it('does not count the path from the sidebar as an applied filter', async () => {
    const { api, globals } = await mountAt('/admin/requests?path=advanced')
    const draft = withSetup(() =>
      useFilterDraft({
        filters: api.filters.filters,
        defaults: api.filterDefaults,
        setFilter: api.chooseFilter,
      }),
    ).api
    expect(api.filters.filters.path).toBe('advanced')
    expect(draft.activeCount.value).toBe(0)
    await globals.router.push('/admin/requests?path=advanced&overdue=true')
    await flushPromises()
    expect(draft.activeCount.value).toBe(1)
  })

  it('takes the stage chosen in the filter dialog into the address and counts it as a filter', async () => {
    const { api, globals } = withSetup(() => {
      const { stage } = useQueueStage()
      return {
        stage,
        queue: useRequestQueue(
          computed(() => ['accredited']),
          stage,
        ),
      }
    })
    await globals.router.push('/admin/requests')
    await flushPromises()
    const draft = withSetup(() =>
      useFilterDraft({
        filters: api.queue.filters.filters,
        defaults: api.queue.filterDefaults,
        setFilter: api.queue.chooseFilter,
      }),
    ).api
    draft.load()
    draft.draft.setFilter('stage', 'overdue')
    draft.apply()
    await flushPromises()
    expect(globals.router.currentRoute.value.query.stage).toBe('overdue')
    expect(lastParams()).toMatchObject({ 'filter[stage]': 'overdue' })
    expect(draft.activeCount.value).toBe(1)
    draft.clear()
    await flushPromises()
    expect(globals.router.currentRoute.value.query.stage).toBeUndefined()
    expect(api.stage.value).toBe('all')
    expect(lastParams()).not.toHaveProperty('filter[stage]')
    expect(draft.activeCount.value).toBe(0)
  })
})
