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

  it('marks only the actions column so it takes the width of its buttons', () => {
    const wrapper = mount(DataTable, {
      props: {
        columns: [
          { key: 'name', label: 'Name' },
          { key: 'actions', label: 'Actions' },
        ],
        items: [{ id: 1, name: 'A' }],
        status: 'success',
        emptyTitle: 'Empty',
      },
      global: createTestGlobals(),
    })
    const marked = (selector) =>
      wrapper.findAll(selector).map((cell) => cell.classes().includes('table__cell--actions'))
    expect(marked('th')).toEqual([false, true])
    expect(marked('tbody td')).toEqual([false, true])
  })

  it('keeps date columns on one line', () => {
    const wrapper = mount(DataTable, {
      props: {
        columns: [
          { key: 'name', label: 'Name' },
          { key: 'createdAt', label: 'Created' },
        ],
        items: [{ id: 1, name: 'A', createdAt: '2026-11-14' }],
        status: 'success',
        emptyTitle: 'Empty',
      },
      global: createTestGlobals(),
    })
    const marked = wrapper
      .findAll('tbody td')
      .map((cell) => cell.classes().includes('table__cell--date'))
    expect(marked).toEqual([false, true])
  })

  it('makes a column with a sortKey a sort button that reports the next order', async () => {
    const sortable = [
      { key: 'entity', label: 'الجهة' },
      { key: 'dueAt', label: 'الاستحقاق', sortKey: 'due_at' },
      { key: 'assignedAt', label: 'تاريخ الإسناد', sortKey: 'assigned_at', sortFirst: 'desc' },
    ]
    const wrapper = factory({ columns: sortable, sort: 'due_at' })
    const headers = wrapper.findAll('th')
    expect(headers[0].attributes('aria-sort')).toBeUndefined()
    expect(headers[0].find('button').exists()).toBe(false)
    expect(headers[1].attributes('aria-sort')).toBe('ascending')
    expect(headers[2].attributes('aria-sort')).toBe('none')
    await headers[1].find('button').trigger('click')
    await headers[2].find('button').trigger('click')
    expect(wrapper.emitted('sort')).toEqual([['-due_at'], ['-assigned_at']])
    await wrapper.setProps({ sort: '-due_at' })
    expect(wrapper.findAll('th')[1].attributes('aria-sort')).toBe('descending')
  })

  describe('one-line rows', () => {
    it('gives every column a kind: the first as primary, dates, status and actions by key', () => {
      const wrapper = factory({
        columns: [
          { key: 'name', label: 'Name' },
          { key: 'email', label: 'Email' },
          { key: 'status', label: 'Status' },
          { key: 'createdAt', label: 'Created' },
          { key: 'total', label: 'Total', kind: 'number' },
          { key: 'actions', label: '' },
        ],
      })
      const kinds = wrapper
        .findAll('thead th')
        .map((th) =>
          th
            .classes()
            .find((name) => name.startsWith('table__cell--') && name !== 'table__cell--sortable'),
        )
      expect(kinds).toEqual([
        'table__cell--primary',
        'table__cell--text',
        'table__cell--status',
        'table__cell--date',
        'table__cell--number',
        'table__cell--actions',
      ])
      expect(wrapper.find('tbody td .table__value').exists()).toBe(true)
    })

    it('shows a cut-off value in full on hover', async () => {
      const wrapper = factory()
      const value = wrapper.find('tbody td .table__value')
      Object.defineProperty(value.element, 'scrollWidth', { value: 300, configurable: true })
      Object.defineProperty(value.element, 'clientWidth', { value: 120, configurable: true })
      await value.trigger('mouseover')
      expect(value.attributes('title')).toBe(value.text())
    })
  })

  describe('stacked mode', () => {
    const stackedColumns = [
      { key: 'entity', label: 'الجهة' },
      { key: 'status', label: 'الحالة', sortKey: 'status' },
      { key: 'open', label: 'فتح', action: true },
    ]

    it('leaves a plain table unstacked: no stacked class or labels', () => {
      const wrapper = factory()
      expect(wrapper.find('.table-wrap').classes()).not.toContain('table-wrap--stacked')
      expect(wrapper.find('tbody td').attributes('data-label')).toBeUndefined()
    })

    it('labels every cell with its column and wraps the cell content', () => {
      const wrapper = factory(
        { stacked: true, columns: stackedColumns },
        { 'cell-status': '<em class="custom">{{ params.item.status }}</em>' },
      )
      expect(wrapper.find('.table-wrap').classes()).toContain('table-wrap--stacked')
      const firstRow = wrapper.findAll('tbody .table__row')[0]
      expect(firstRow.findAll('td').map((cell) => cell.attributes('data-label'))).toEqual([
        'الجهة',
        'الحالة',
        'فتح',
      ])
      expect(firstRow.findAll('.table__value')).toHaveLength(3)
      expect(firstRow.find('.table__value em.custom').text()).toBe('under-review')
    })

    it('treats a column marked as an action like the actions column', () => {
      const wrapper = factory({ stacked: true, columns: stackedColumns })
      const marked = wrapper
        .findAll('tbody .table__row')[0]
        .findAll('td')
        .map((cell) => cell.classes().includes('table__cell--actions'))
      expect(marked).toEqual([false, false, true])
    })

    it('marks sortable headings so they stay reachable when the rows stack', () => {
      const wrapper = factory({ stacked: true, columns: stackedColumns })
      const sortable = wrapper
        .findAll('th')
        .map((cell) => cell.classes().includes('table__cell--sortable'))
      expect(sortable).toEqual([false, true, false])
    })

    it('labels skeleton cells while loading', () => {
      const wrapper = factory({ stacked: true, columns: stackedColumns, status: 'loading' })
      expect(wrapper.find('tbody td').attributes('data-label')).toBe('الجهة')
    })
  })
})
