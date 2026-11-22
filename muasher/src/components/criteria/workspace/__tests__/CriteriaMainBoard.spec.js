import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import CriteriaMainBoard from '@/components/criteria/workspace/CriteriaMainBoard.vue'
import CriteriaMainStrip from '@/components/criteria/workspace/CriteriaMainStrip.vue'
import CriteriaWeightSplit from '@/components/criteria/workspace/CriteriaWeightSplit.vue'
import { criteriaService } from '@/services/criteriaService'
import { indexTree } from '@/utils/criteriaTree'
import { createTestGlobals } from '@/test-utils'
import { workspaceTree } from './criteriaTreeFixture'

vi.mock('@/services/criteriaService', () => ({
  criteriaService: { updateMain: vi.fn() },
}))

const globals = () => createTestGlobals({ locale: 'en' })
let index

beforeEach(() => {
  index = indexTree(workspaceTree())
  criteriaService.updateMain.mockReset().mockResolvedValue({})
})

function mountBoard(key = 'main-11', props = {}) {
  return mount(CriteriaMainBoard, {
    props: {
      item: index.get(key),
      index,
      modelWeight: '1500.00',
      canEdit: true,
      reload: vi.fn(),
      ...props,
    },
    global: globals(),
  })
}

describe('CriteriaMainStrip', () => {
  const mains = () => [...index.values()].filter((item) => !item.parentKey)

  it('lists the main criteria with their share of the model and marks the ones to fix', async () => {
    index.get('main-12').attention = true
    const wrapper = mount(CriteriaMainStrip, {
      props: { mains: mains(), selectedKey: 'main-11', modelWeight: '1500.00', canEdit: false },
      global: globals(),
    })
    const steps = wrapper.findAll('.criteria-strip__step')
    expect(steps[0].text()).toContain('Leadership')
    expect(steps[0].text()).toContain('66.7%')
    expect(steps[0].text()).toContain('2 sub-criteria')
    expect(steps[0].attributes('aria-current')).toBe('true')
    expect(steps[1].classes()).toContain('is-attention')
    expect(wrapper.text()).not.toContain('Add a main criterion')
    await steps[1].trigger('click')
    expect(wrapper.emitted('select')[0]).toEqual(['main-12'])
  })
})

describe('CriteriaWeightSplit', () => {
  it('shows each share and the missing weight as a gap', () => {
    const wrapper = mount(CriteriaWeightSplit, {
      props: {
        parentWeight: '1000.00',
        items: [
          { key: 'a', code: 'M1.1', weight: '600.00', active: true },
          { key: 'b', code: 'M1.2', weight: '300.00', active: true },
          { key: 'c', code: 'M1.3', weight: '50.00', active: false },
        ],
      },
      global: globals(),
    })
    expect(wrapper.findAll('.criteria-split__bar .criteria-split__segment')).toHaveLength(3)
    expect(wrapper.text()).toContain('60%')
    expect(wrapper.text()).toContain('Remaining 100.00')
    expect(wrapper.text()).not.toContain('M1.3')
  })

  it('flags a split that goes over the parent weight', () => {
    const wrapper = mount(CriteriaWeightSplit, {
      props: {
        parentWeight: '100.00',
        items: [{ key: 'a', code: 'M1.1', weight: '120.00', active: true }],
      },
      global: globals(),
    })
    expect(wrapper.find('.criteria-split').classes()).toContain('is-over')
    expect(wrapper.text()).toContain('Over by 20.00')
  })
})

describe('CriteriaMainBoard', () => {
  it('shows the main criterion at full width with its sub-criteria and indicators', () => {
    const wrapper = mountBoard()
    expect(wrapper.find('.criteria-board__title').text()).toBe('Leadership')
    expect(wrapper.text()).toContain('66.7%')
    const cards = wrapper.findAll('.criteria-card')
    expect(cards).toHaveLength(2)
    expect(cards[0].text()).toContain('Planning')
    expect(cards[0].text()).toContain('60%')
    expect(cards[0].text()).toContain('Approved plan')
    expect(cards[0].text()).toContain('Plan')
    expect(cards[1].text()).toContain('Scored directly with one judgment')
  })

  it('lists the descriptive indicators of a direct sub-criterion without a type or a share', () => {
    const direct = mountBoard().findAll('.criteria-card')[1]
    const row = direct.find('.criteria-point')
    expect(row.text()).toContain('Approved policy')
    expect(row.text()).toContain('Descriptive')
    expect(row.text()).toContain('Minutes')
    expect(row.find('.criteria-share').exists()).toBe(false)
    expect(direct.text()).toContain('Add indicator')
  })

  it('opens the editor of the item a button belongs to', async () => {
    const wrapper = mountBoard()
    const editOf = (scope) => scope.findAll('button').find((button) => button.text() === 'Edit')
    await editOf(wrapper.find('.criteria-board__head')).trigger('click')
    await editOf(wrapper.find('.criteria-card__head')).trigger('click')
    await editOf(wrapper.find('.criteria-point')).trigger('click')
    expect(wrapper.emitted('open').map(([key]) => key)).toEqual(['main-11', 'sub-21', 'point-31'])
  })

  it('splits a full main criterion equally before adding a sub-criterion', async () => {
    const wrapper = mountBoard()
    await wrapper.find('.criteria-add-row:not(.criteria-add-row--sm)').trigger('click')
    await flushPromises()
    expect(criteriaService.updateMain).toHaveBeenCalledWith(11, { distributeEqually: true })
    expect(wrapper.emitted('add')[0]).toEqual(['main-11'])
  })

  it('reorders the sub-criteria with the arrow buttons', async () => {
    const wrapper = mountBoard()
    await wrapper.find('.criteria-card__head .reorder-buttons button:last-child').trigger('click')
    expect(wrapper.emitted('reorder')[0]).toEqual([
      { type: 'sub', parentKey: 'main-11', ids: [22, 21] },
    ])
  })

  it('offers to fix the weights of an unbalanced main criterion', async () => {
    index.get('main-11').attention = true
    const wrapper = mountBoard()
    expect(wrapper.find('.criteria-board__alert').exists()).toBe(true)
    await wrapper
      .findAll('.criteria-board__alert button')
      .find((button) => button.text() === 'Edit the split by hand')
      .trigger('click')
    expect(wrapper.emitted('open')[0]).toEqual(['main-11', 'weight'])
  })

  it('shows details only, without edit or add buttons, without criteria.manage', () => {
    const wrapper = mountBoard('main-11', { canEdit: false })
    expect(wrapper.text()).not.toContain('Edit')
    expect(wrapper.find('.criteria-add-row').exists()).toBe(false)
    expect(wrapper.text()).toContain('Details')
  })
})
