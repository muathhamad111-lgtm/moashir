import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import CriteriaScoringCard from '@/components/criteria/workspace/CriteriaScoringCard.vue'
import { criteriaService } from '@/services/criteriaService'
import { indexTree } from '@/utils/criteriaTree'
import { createTestGlobals } from '@/test-utils'
import { workspaceTree } from './criteriaTreeFixture'

vi.mock('@/services/criteriaService', () => ({
  criteriaService: {
    updateMain: vi.fn(),
    updateSub: vi.fn(),
    applyPointScoringType: vi.fn(),
  },
}))

const index = indexTree(workspaceTree())

function mountCard(key, props = {}) {
  const reload = vi.fn()
  const wrapper = mount(CriteriaScoringCard, {
    props: { item: index.get(key), canEdit: true, reload, ...props },
    global: createTestGlobals({ locale: 'en' }),
  })
  return { wrapper, reload }
}

function buttonNamed(wrapper, label) {
  return wrapper.findAll('button').find((button) => button.text() === label)
}

beforeEach(() => {
  vi.clearAllMocks()
  criteriaService.updateMain.mockResolvedValue({ data: {} })
  criteriaService.updateSub.mockResolvedValue({ data: {} })
  criteriaService.applyPointScoringType.mockResolvedValue({ data: {} })
})

describe('CriteriaScoringCard', () => {
  it('saves the default type for the new sub-criteria of a main criterion', async () => {
    const { wrapper, reload } = mountCard('main-11')
    expect(wrapper.find('.card__header').text()).toBe('Default scoring type')
    await wrapper.find('input[value="achievement"]').setValue(true)
    await buttonNamed(wrapper, 'Save').trigger('click')
    await flushPromises()
    expect(criteriaService.updateMain).toHaveBeenCalledWith(11, {
      defaultScoringType: 'achievement',
    })
    expect(reload).toHaveBeenCalled()
  })

  it('saves the own type of a directly evaluated sub-criterion', async () => {
    const { wrapper } = mountCard('sub-22')
    expect(wrapper.find('input[value="score"]').element.checked).toBe(true)
    await wrapper.find('input[value="relative"]').setValue(true)
    await buttonNamed(wrapper, 'Save').trigger('click')
    await flushPromises()
    expect(criteriaService.updateSub).toHaveBeenCalledWith(22, { scoringType: 'relative' })
    expect(buttonNamed(wrapper, 'Apply to all indicators')).toBeUndefined()
  })

  it('applies one type to every indicator of a sub-criterion', async () => {
    const { wrapper } = mountCard('sub-21')
    await wrapper.find('input[value="achievement"]').setValue(true)
    await buttonNamed(wrapper, 'Apply to all indicators').trigger('click')
    await flushPromises()
    expect(criteriaService.applyPointScoringType).toHaveBeenCalledWith(21, 'achievement')
  })

  it('reads only without criteria.manage', () => {
    const { wrapper } = mountCard('sub-21', { canEdit: false })
    expect(wrapper.find('fieldset').attributes('disabled')).toBeDefined()
    expect(wrapper.find('.card__footer').exists()).toBe(false)
  })
})
