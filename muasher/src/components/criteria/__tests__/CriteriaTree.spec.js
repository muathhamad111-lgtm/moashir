import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CriteriaTree from '@/components/criteria/CriteriaTree.vue'
import { createTestGlobals } from '@/test-utils'

const nodes = [
  {
    id: 1,
    label: 'Administrative Leadership',
    active: true,
    children: [
      {
        id: 101,
        label: 'Governance',
        active: true,
        children: [{ id: 1001, label: 'Governance charter', meta: '180' }],
      },
      { id: 102, label: 'Excellence culture', active: false },
    ],
  },
  { id: 2, label: 'Strategy', active: true, children: [] },
]

function mountTree(props = {}) {
  return mount(CriteriaTree, {
    props: { nodes, ...props },
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('CriteriaTree', () => {
  it('renders an empty message with no nodes', () => {
    const wrapper = mountTree({ nodes: [] })
    expect(wrapper.find('.criteria-tree__empty').exists()).toBe(true)
  })

  it('opens the first branch by default and exposes its children', () => {
    const wrapper = mountTree()
    expect(wrapper.find('.criteria-tree__branch.is-open').exists()).toBe(true)
    expect(wrapper.text()).toContain('Governance')
  })

  it('toggles a branch open and closed', async () => {
    const wrapper = mountTree()
    const toggles = wrapper.findAll('.criteria-tree__toggle')
    await toggles[1].trigger('click')
    expect(wrapper.findAll('.criteria-tree__branch.is-open')).toHaveLength(2)
    await toggles[0].trigger('click')
    expect(wrapper.findAll('.criteria-tree__branch.is-open')).toHaveLength(1)
  })

  it('emits select with the main node when the open button is clicked', async () => {
    const wrapper = mountTree()
    await wrapper.find('.criteria-tree__open').trigger('click')
    const event = wrapper.emitted('select')[0][0]
    expect(event.level).toBe('main')
    expect(event.node.id).toBe(1)
  })

  it('emits select with the sub node and its parent', async () => {
    const wrapper = mountTree()
    await wrapper.find('.criteria-tree__child-btn').trigger('click')
    const event = wrapper.emitted('select')[0][0]
    expect(event.level).toBe('sub')
    expect(event.node.id).toBe(101)
    expect(event.parent.id).toBe(1)
  })

  it('marks inactive children visually', () => {
    const wrapper = mountTree()
    expect(wrapper.findAll('.criteria-tree__child.is-inactive').length).toBeGreaterThan(0)
  })
})
