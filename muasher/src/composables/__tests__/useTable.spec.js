import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useTable } from '@/composables/useTable'
import { useFilters } from '@/composables/useFilters'
import { usePagination } from '@/composables/usePagination'

function harness(options) {
  let table
  const Host = defineComponent({
    setup() {
      table = useTable(options)
      return () => h('div')
    },
  })
  const wrapper = mount(Host)
  return { table, wrapper }
}

const envelope = {
  data: [{ id: 1, name: 'جمعية البر' }],
  meta: { pagination: { page: 1, perPage: 15, total: 1, lastPage: 1 } },
  errors: null,
}

describe('useTable', () => {
  it('loads through the fetcher and lands on success with items', async () => {
    const fetcher = vi.fn().mockResolvedValue(envelope)
    const { table } = harness({ fetcher })
    expect(table.status.value).toBe('loading')
    await flushPromises()
    expect(table.status.value).toBe('success')
    expect(table.items.value).toHaveLength(1)
  })

  it('lands on empty when the envelope has no rows', async () => {
    const fetcher = vi.fn().mockResolvedValue({ ...envelope, data: [] })
    const { table } = harness({ fetcher })
    await flushPromises()
    expect(table.status.value).toBe('empty')
  })

  it('lands on error, exposes the normalized message, and retries via load', async () => {
    const error = new Error('boom')
    error.normalized = { message: 'تعذر تحميل البيانات', fieldErrors: {}, code: 500 }
    const fetcher = vi.fn().mockRejectedValueOnce(error).mockResolvedValueOnce(envelope)
    const { table } = harness({ fetcher })
    await flushPromises()
    expect(table.status.value).toBe('error')
    expect(table.errorMessage.value).toBe('تعذر تحميل البيانات')
    await table.load()
    expect(table.status.value).toBe('success')
  })

  it('merges filters + pagination params and applies envelope pagination meta', async () => {
    const fetcher = vi.fn().mockResolvedValue({
      ...envelope,
      meta: { pagination: { page: 1, perPage: 15, total: 60, lastPage: 4 } },
    })
    const filters = useFilters({ status: '' })
    const pagination = usePagination()
    filters.search.value = 'جمعية'
    const { table } = harness({ fetcher, filters, pagination })
    await flushPromises()
    expect(fetcher).toHaveBeenCalledWith({ search: 'جمعية', page: 1, perPage: 15 })
    expect(pagination.pagination.lastPage).toBe(4)
    expect(table.status.value).toBe('success')
  })

  it('reloads when reactive params change', async () => {
    const fetcher = vi.fn((params) =>
      Promise.resolve({
        data: [{ id: 1 }],
        meta: { pagination: { page: params.page, perPage: 15, total: 30, lastPage: 2 } },
        errors: null,
      }),
    )
    const pagination = usePagination()
    harness({ fetcher, pagination })
    await flushPromises()
    pagination.setPage(2)
    await flushPromises()
    expect(fetcher).toHaveBeenLastCalledWith({ page: 2, perPage: 15 })
  })
})
