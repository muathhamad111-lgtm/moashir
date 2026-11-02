import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import DataTable from '@/components/data-list/DataTable.vue'
import { createTestGlobals } from '@/test-utils'

const columns = [
  { key: 'entity', label: 'الجهة' },
  { key: 'status', label: 'الحالة' },
]
const items = [
  { id: 11, entity: 'جمعية البر بمنطقة الرياض', status: 'under-review' },
  { id: 12, entity: 'جمعية إنسان', status: 'completed' },
]

function factory(props = {}, slots = {}) {
  return mount(DataTable, {
    props: { columns, items, status: 'success', ...props },
    slots,
    global: createTestGlobals(),
  })
}

describe('DataTable', () => {
  it('renders headers and rows in the success state', () => {
    const wrapper = factory()
    expect(wrapper.findAll('th')).toHaveLength(2)
    expect(wrapper.findAll('tbody .table__row')).toHaveLength(2)
    expect(wrapper.text()).toContain('جمعية البر بمنطقة الرياض')
  })

  it('supports per-column cell slots', () => {
    const wrapper = factory(
      {},
      { 'cell-status': '<em class="custom">{{ params.item.status }}</em>' },
    )
    expect(wrapper.findAll('em.custom')).toHaveLength(2)
  })

  it('renders skeleton rows and aria-busy while loading', () => {
    const wrapper = factory({ status: 'loading' })
    expect(wrapper.find('.table--loading').exists()).toBe(true)
    expect(wrapper.find('table').attributes('aria-busy')).toBe('true')
    expect(wrapper.findAll('.skeleton').length).toBeGreaterThan(0)
  })

  it('renders a branded EmptyState when empty', () => {
    const wrapper = factory({ status: 'empty', items: [] })
    expect(wrapper.find('.empty-state').exists()).toBe(true)
    expect(wrapper.find('.empty-state__title').text()).toBe('لا توجد بيانات للعرض حاليًا')
  })

  it('renders ErrorState with retry emitting to the parent', async () => {
    const wrapper = factory({ status: 'error', items: [] })
    expect(wrapper.find('.error-state').exists()).toBe(true)
    await wrapper.find('.error-state__retry button').trigger('click')
    expect(wrapper.emitted('retry')).toHaveLength(1)
  })
})
