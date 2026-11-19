import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CriteriaTree from '@/components/criteria/workspace/CriteriaTree.vue'
import { indexTree } from '@/utils/criteriaTree'
import { createTestGlobals } from '@/test-utils'
import { workspaceTree } from './criteriaTreeFixture'

function mountTree(props = {}) {
  return mount(CriteriaTree, {
    props: { index: indexTree(workspaceTree()), selectedKey: null, canEdit: true, ...props },
    global: createTestGlobals({ locale: 'en' }),
  })
}

function names(wrapper) {
  return wrapper.findAll('.criteria-tree__name').map((name) => name.text())
}

function buttonLabelled(wrapper, label) {
  return wrapper.findAll('button').find((button) => button.attributes('aria-label') === label)
}

describe('CriteriaTree', () => {
  it('lists the main criteria collapsed and opens the path to the selected node', () => {
    expect(names(mountTree())).toEqual(['Leadership', 'Results'])
    expect(names(mountTree({ selectedKey: 'point-31' }))).toEqual([
      'Leadership',
      'Planning',
      'Approved plan',
      'Governance',
      'Results',
    ])
  })

  it('expands a node, selects it and marks what needs fixing', async () => {
    const wrapper = mountTree()
    await buttonLabelled(wrapper, 'Show or hide the items under Leadership').trigger('click')
    expect(names(wrapper)).toContain('Planning')
    const planning = wrapper.findAll('.criteria-tree__row')[1]
    expect(planning.find('.criteria-tree__marker--attention').exists()).toBe(true)
    await planning.find('.criteria-tree__select').trigger('click')
    expect(wrapper.emitted('select')[0]).toEqual(['sub-21'])
  })

  it('filters by name and by what needs fixing', async () => {
    const wrapper = mountTree()
    await wrapper.find('input[name="criteriaSearch"]').setValue('approved')
    expect(names(wrapper)).toEqual(['Leadership', 'Planning', 'Approved plan'])
    await wrapper.find('input[name="criteriaSearch"]').setValue('nothing like this')
    expect(wrapper.find('.criteria-tree__empty').text()).toBe('No matching criteria.')
    await wrapper.find('input[name="criteriaSearch"]').setValue('')
    await wrapper.find('input[name="criteriaAttentionOnly"]').setValue(true)
    expect(names(wrapper)).toEqual(['Leadership', 'Planning'])
  })

  it('adds children only where they belong and reorders siblings', async () => {
    const wrapper = mountTree({ selectedKey: 'sub-21' })
    await buttonLabelled(wrapper, 'Add a sub-criterion to Leadership').trigger('click')
    await buttonLabelled(wrapper, 'Add an indicator to Planning').trigger('click')
    expect(wrapper.emitted('add')).toEqual([['main-11'], ['sub-21']])
    expect(buttonLabelled(wrapper, 'Add an indicator to Governance')).toBeUndefined()
    await buttonLabelled(wrapper, 'Move Planning down').trigger('click')
    expect(wrapper.emitted('reorder')[0]).toEqual([
      { type: 'sub', parentKey: 'main-11', ids: [22, 21] },
    ])
  })

  it('offers no editing controls when the version is read only', () => {
    const wrapper = mountTree({ canEdit: false })
    expect(wrapper.text()).not.toContain('Add main criterion')
    expect(wrapper.find('.criteria-tree__moves').exists()).toBe(false)
  })
})
