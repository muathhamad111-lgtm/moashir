import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import CriteriaWeightsCard from '@/components/criteria/CriteriaWeightsCard.vue'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/composables/useToast', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const ITEMS = [
  { id: 1, label: 'Policies', weight: '200.00', active: true },
  { id: 2, label: 'Compliance', weight: '150.00', active: true },
]

function mountCard(props = {}) {
  const saveWeights = vi.fn().mockResolvedValue({})
  const wrapper = mount(CriteriaWeightsCard, {
    props: {
      title: 'Weights',
      parentWeight: '400.00',
      items: ITEMS,
      saveMode: vi.fn().mockResolvedValue({}),
      saveWeights,
      reload: vi.fn().mockResolvedValue(),
      level: 'points',
      ...props,
    },
    global: createTestGlobals({ locale: 'en' }),
  })
  return { wrapper, saveWeights }
}

function saveButton(wrapper) {
  return wrapper.findAll('button').find((button) => button.text() === 'Save weights')
}

async function typeWeight(wrapper, index, value) {
  await wrapper.findAll('.weight-editor__input')[index].setValue(value)
}

describe('CriteriaWeightsCard', () => {
  it('speaks about the guiding points and the sub-criterion at the points level', () => {
    const { wrapper } = mountCard()

    expect(wrapper.find('.weight-editor__status').text()).toContain(
      'left to complete the sub-criterion weight',
    )
  })

  it('saves every changed weight in one request', async () => {
    const { wrapper, saveWeights } = mountCard()

    await typeWeight(wrapper, 0, '250')
    await typeWeight(wrapper, 1, '150')
    await saveButton(wrapper).trigger('click')
    await flushPromises()

    expect(saveWeights).toHaveBeenCalledTimes(1)
    expect(saveWeights).toHaveBeenCalledWith([{ id: 1, weight: '250.00' }])
  })

  it('keeps Save disabled until a weight changes and while the weights exceed the parent', async () => {
    const { wrapper } = mountCard()
    expect(saveButton(wrapper).attributes('disabled')).toBeDefined()

    await typeWeight(wrapper, 0, '300')
    expect(wrapper.find('.weight-editor__status--danger').exists()).toBe(true)
    expect(saveButton(wrapper).attributes('disabled')).toBeDefined()

    await typeWeight(wrapper, 0, '250')
    expect(saveButton(wrapper).attributes('disabled')).toBeUndefined()
  })

  it('hides the manual actions under equal distribution', () => {
    const { wrapper } = mountCard({ distributeEqually: true })

    expect(saveButton(wrapper)).toBeUndefined()
  })
})
