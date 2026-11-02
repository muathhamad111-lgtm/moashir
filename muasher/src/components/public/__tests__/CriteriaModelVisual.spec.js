import { describe, expect, it, vi } from 'vitest'
import { ref, computed } from 'vue'
import { mount } from '@vue/test-utils'
import CriteriaModelVisual from '@/components/public/CriteriaModelVisual.vue'
import { createTestGlobals } from '@/test-utils'

const state = {
  status: ref('success'),
  criteria: ref([]),
}

function group(name) {
  return computed(() => state.criteria.value.filter((item) => item.group === name))
}

vi.mock('@/composables/useModelCriteria', () => ({
  useModelCriteria: () => ({
    status: state.status,
    criteria: state.criteria,
    enablers: group('enablers'),
    creativity: group('creativity_learning'),
    results: group('results'),
    load: vi.fn(),
    localized: (content) => (typeof content === 'string' ? content : (content?.en ?? '')),
  }),
}))

function mountVisual() {
  return mount(CriteriaModelVisual, {
    global: { plugins: createTestGlobals({ locale: 'en' }).plugins },
  })
}

describe('CriteriaModelVisual', () => {
  it('renders the three model groups from the published criteria without weights', () => {
    state.status.value = 'success'
    state.criteria.value = [
      {
        id: 1,
        code: 'M1',
        order: 1,
        group: 'enablers',
        name: { ar: 'القيادة', en: 'Leadership' },
        description: null,
      },
      {
        id: 6,
        code: 'M6',
        order: 6,
        group: 'creativity_learning',
        name: { ar: 'الابتكار', en: 'Innovation' },
        description: null,
      },
      {
        id: 8,
        code: 'M8',
        order: 8,
        group: 'results',
        name: { ar: 'النتائج', en: 'Beneficiary Results' },
        description: null,
      },
    ]
    const wrapper = mountVisual()
    expect(wrapper.findAll('.criteria-model__cell--enablers')).toHaveLength(1)
    expect(wrapper.findAll('.criteria-model__cell--creativity')).toHaveLength(1)
    expect(wrapper.find('.criteria-model__cell--results .criteria-model__name').text()).toBe(
      'Beneficiary Results',
    )
    expect(wrapper.findAll('.criteria-model__arrow')).toHaveLength(2)
    expect(wrapper.text()).not.toMatch(/Weight/)
  })

  it('shows an error state with retry', () => {
    state.status.value = 'error'
    state.criteria.value = []
    expect(mountVisual().find('.error-state').exists()).toBe(true)
  })

  it('shows a not-published empty state', () => {
    state.status.value = 'empty'
    state.criteria.value = []
    expect(mountVisual().find('.empty-state').exists()).toBe(true)
  })
})
