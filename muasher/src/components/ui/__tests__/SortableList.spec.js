import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import SortableList from '@/components/ui/SortableList.vue'
import { createTestGlobals } from '@/test-utils'

const items = [
  { id: 1, name: 'Hero' },
  { id: 2, name: 'Paths' },
  { id: 3, name: 'Journey' },
]

function mountList(props = {}) {
  return mount(SortableList, {
    props: { items, labelOf: (item) => item.name, listLabel: 'Sections', ...props },
    slots: { item: '<template #item="{ item }"><span class="name">{{ item.name }}</span></template>' },
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('SortableList', () => {
  it('renders draggable rows with labelled move buttons', () => {
    const wrapper = mountList()
    const rows = wrapper.findAll('.sortable-list__row')
    expect(rows.map((row) => row.find('.name').text())).toEqual(['Hero', 'Paths', 'Journey'])
    expect(rows[0].attributes('draggable')).toBe('true')
    const firstButtons = rows[0].findAll('button')
    expect(firstButtons[0].attributes('disabled')).toBeDefined()
    expect(firstButtons[1].attributes('aria-label')).toBe('Move “Hero” down')
  })

  it('emits the complete new order after a button move or a drop', async () => {
    const wrapper = mountList()
    await wrapper.findAll('.sortable-list__row')[1].findAll('button')[0].trigger('click')
    expect(wrapper.emitted('reorder')[0][0]).toEqual([2, 1, 3])
    const rows = wrapper.findAll('.sortable-list__row')
    await rows[0].trigger('dragstart', { dataTransfer: { setData: vi.fn(), effectAllowed: '' } })
    await rows[2].trigger('dragover')
    expect(rows[2].classes()).toContain('is-over')
    await rows[2].trigger('drop')
    expect(wrapper.emitted('reorder')[1][0]).toEqual([2, 3, 1])
  })

  it('locks dragging and moving while disabled', () => {
    const wrapper = mountList({ disabled: true })
    expect(wrapper.find('.sortable-list__row').attributes('draggable')).toBe('false')
    expect(wrapper.findAll('button').every((button) => button.attributes('disabled') !== undefined)).toBe(true)
  })
})
