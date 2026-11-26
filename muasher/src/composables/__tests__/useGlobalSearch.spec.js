import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { defineComponent, nextTick } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useGlobalSearch } from '@/composables/useGlobalSearch'
import { searchService } from '@/services/searchService'

vi.mock('@/services/searchService', () => ({
  searchService: { search: vi.fn() },
}))

function harness() {
  let api
  const Host = defineComponent({
    setup() {
      api = useGlobalSearch({ debounceMs: 300 })
      return () => null
    },
  })
  const wrapper = mount(Host)
  return { api, wrapper }
}

function results(groups) {
  return { data: { groups } }
}

const entityGroup = {
  type: 'entities',
  total: 1,
  items: [{ type: 'entities', key: 'entities:1', id: 1, title: 'جمعية' }],
}

beforeEach(() => {
  vi.useFakeTimers()
  vi.clearAllMocks()
})

afterEach(() => {
  vi.useRealTimers()
})

describe('useGlobalSearch', () => {
  it('stays idle for an empty query and asks for 2 characters before searching', async () => {
    const { api } = harness()
    expect(api.status.value).toBe('idle')
    api.query.value = 'ج'
    await nextTick()
    expect(api.status.value).toBe('too-short')
    vi.advanceTimersByTime(1000)
    expect(searchService.search).not.toHaveBeenCalled()
  })

  it('debounces typing and sends one trimmed request', async () => {
    searchService.search.mockResolvedValue(results([entityGroup]))
    const { api } = harness()
    api.query.value = 'جم'
    await nextTick()
    api.query.value = ' جمعية '
    await nextTick()
    expect(api.status.value).toBe('loading')
    vi.advanceTimersByTime(299)
    expect(searchService.search).not.toHaveBeenCalled()
    vi.advanceTimersByTime(1)
    await flushPromises()
    expect(searchService.search).toHaveBeenCalledTimes(1)
    expect(searchService.search).toHaveBeenCalledWith('جمعية')
    expect(api.status.value).toBe('success')
    expect(api.options.value).toHaveLength(1)
  })

  it('hides empty groups and reports empty when nothing matched', async () => {
    searchService.search.mockResolvedValue(results([{ type: 'users', total: 0, items: [] }]))
    const { api } = harness()
    api.query.value = 'وقف'
    await nextTick()
    vi.advanceTimersByTime(300)
    await flushPromises()
    expect(api.groups.value).toEqual([])
    expect(api.status.value).toBe('empty')
  })

  it('ignores a stale response that resolves after a newer query', async () => {
    let resolveFirst
    searchService.search
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            resolveFirst = resolve
          }),
      )
      .mockResolvedValueOnce(results([]))
    const { api } = harness()
    api.query.value = 'نماء'
    await nextTick()
    vi.advanceTimersByTime(300)
    api.query.value = 'إحسان'
    await nextTick()
    vi.advanceTimersByTime(300)
    await flushPromises()
    resolveFirst(results([entityGroup]))
    await flushPromises()
    expect(api.status.value).toBe('empty')
    expect(api.groups.value).toEqual([])
  })

  it('shows the error state and retries on demand', async () => {
    searchService.search
      .mockRejectedValueOnce(new Error('offline'))
      .mockResolvedValueOnce(results([entityGroup]))
    const { api } = harness()
    api.query.value = 'عطاء'
    await nextTick()
    vi.advanceTimersByTime(300)
    await flushPromises()
    expect(api.status.value).toBe('error')
    api.retry()
    await flushPromises()
    expect(api.status.value).toBe('success')
  })

  it('clears back to idle', async () => {
    const { api } = harness()
    api.query.value = 'سقيا'
    await nextTick()
    api.clear()
    await nextTick()
    expect(api.status.value).toBe('idle')
    vi.advanceTimersByTime(1000)
    expect(searchService.search).not.toHaveBeenCalled()
  })
})
