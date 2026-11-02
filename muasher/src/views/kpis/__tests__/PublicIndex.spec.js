import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import PublicIndex from '@/views/kpis/PublicIndex.vue'
import { createTestGlobals } from '@/test-utils'

const status = ref('success')
const load = vi.fn()

function makeKpi(overrides) {
  return {
    id: overrides.id,
    slug: overrides.slug ?? `kpi-${overrides.id}`,
    name: overrides.name,
    value: overrides.value ?? 100,
    target: overrides.target ?? 200,
    baseline: overrides.baseline ?? 50,
    unit: overrides.unit ?? { ar: 'وحدة', en: 'unit' },
    description: overrides.description ?? { ar: 'وصف', en: 'Description' },
    category: overrides.category,
    status: overrides.status,
    lastUpdate: overrides.lastUpdate ?? '2025-01-01',
    icon: 'fa-solid fa-chart-line',
    color: 'primary',
    cardColor: overrides.cardColor ?? 'primary',
    order: overrides.order ?? overrides.id,
    published: true,
  }
}

const kpis = ref([
  makeKpi({
    id: 1,
    name: { ar: 'الجمعيات المشاركة', en: 'Participating associations' },
    category: 'beneficiaries',
    status: 'good',
    value: 486,
  }),
  makeKpi({
    id: 2,
    name: { ar: 'التميز المؤسسي', en: 'Institutional excellence' },
    category: 'institutional-excellence',
    status: 'excellent',
    value: 132,
  }),
  makeKpi({
    id: 3,
    name: { ar: 'رضا المستفيدين', en: 'Beneficiary satisfaction' },
    category: 'beneficiaries',
    status: 'needs-improvement',
    value: 78,
  }),
])

vi.mock('@/composables/useKpis', () => ({
  useKpis: () => ({
    kpis,
    status,
    load,
    localized: (value) => (typeof value === 'string' ? value : (value?.en ?? '')),
  }),
}))

function mountView() {
  return mount(PublicIndex, { global: createTestGlobals({ locale: 'en' }) })
}

describe('KPI public index (premium redesign)', () => {
  it('renders a card per published KPI with a status badge', () => {
    status.value = 'success'
    const wrapper = mountView()
    const cards = wrapper.findAll('.kpi-card')
    expect(cards).toHaveLength(3)
    expect(wrapper.findAll('.badge').length).toBeGreaterThanOrEqual(3)
    expect(cards[0].find('.kpi-card__name').text()).toBe('Participating associations')
  })

  it('narrows the grid when a category tab is selected', async () => {
    status.value = 'success'
    const wrapper = mountView()
    expect(wrapper.findAll('.kpi-card')).toHaveLength(3)

    const beneficiariesTab = wrapper
      .findAll('.tabs__tab')
      .find((tab) => tab.text() === 'Beneficiaries')
    expect(beneficiariesTab).toBeTruthy()
    await beneficiariesTab.trigger('click')

    const names = wrapper.findAll('.kpi-card__name').map((el) => el.text())
    expect(names).toEqual(['Participating associations', 'Beneficiary satisfaction'])
  })

  it('filters the grid by the real-time search term', async () => {
    status.value = 'success'
    const wrapper = mountView()
    await wrapper.find('input[type="search"]').setValue('Institutional')
    const names = wrapper.findAll('.kpi-card__name').map((el) => el.text())
    expect(names).toEqual(['Institutional excellence'])
  })

  it('shows a no-results empty state when nothing matches the search', async () => {
    status.value = 'success'
    const wrapper = mountView()
    await wrapper.find('input[type="search"]').setValue('zzz-nothing')
    expect(wrapper.findAll('.kpi-card')).toHaveLength(0)
    expect(wrapper.find('.empty-state').exists()).toBe(true)
  })

  it('renders skeletons while loading and an error state on failure', async () => {
    status.value = 'loading'
    let wrapper = mountView()
    expect(wrapper.findAll('.skeleton').length).toBeGreaterThan(0)

    status.value = 'error'
    wrapper = mountView()
    await wrapper.find('.error-state button').trigger('click')
    expect(load).toHaveBeenCalled()
  })
})
