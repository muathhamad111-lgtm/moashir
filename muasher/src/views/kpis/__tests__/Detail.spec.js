import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import Detail from '@/views/kpis/Detail.vue'
import { createTestGlobals } from '@/test-utils'

const status = ref('success')
const kpi = ref(null)
const load = vi.fn()

vi.mock('@/composables/useKpiDetail', () => ({
  useKpiDetail: () => ({
    kpi,
    status,
    load,
    localized: (value) => (typeof value === 'string' ? value : (value?.en ?? '')),
  }),
}))

function sampleKpi(overrides = {}) {
  return {
    id: 1,
    slug: 'participating-associations',
    name: { ar: 'الجمعيات المشاركة', en: 'Participating associations' },
    description: { ar: 'وصف', en: 'Registered associations on the platform' },
    value: 486,
    target: 700,
    baseline: 320,
    unit: { ar: 'جمعية', en: 'associations' },
    category: 'beneficiaries',
    status: 'good',
    owner: { ar: 'الإدارة', en: 'Registration Department' },
    dataSource: { ar: 'السجل', en: 'Platform log' },
    lastUpdate: '2025-01-15',
    externalLink: 'https://muasher.sa/associations',
    calcMethod: { ar: 'طريقة', en: 'Count of active entities' },
    notes: { ar: 'ملاحظات', en: 'Updated quarterly' },
    icon: 'fa-solid fa-people-group',
    color: 'primary',
    cardColor: 'primary',
    ...overrides,
  }
}

function mountView() {
  return mount(Detail, { global: createTestGlobals({ locale: 'en' }) })
}

beforeEach(() => {
  load.mockClear()
})

describe('KPI detail (per-slug page)', () => {
  it('loads the KPI by the route slug on mount', () => {
    status.value = 'success'
    kpi.value = sampleKpi()
    mountView()
    expect(load).toHaveBeenCalled()
  })

  it('renders the resolved indicator content in a premium layout', () => {
    status.value = 'success'
    kpi.value = sampleKpi()
    const wrapper = mountView()
    expect(wrapper.find('.kpi-detail__title').text()).toBe('Participating associations')
    expect(wrapper.find('.kpi-detail__number').text()).toBe('486')
    expect(wrapper.find('.kpi-detail__unit').text()).toBe('associations')
    expect(wrapper.text()).toContain('Count of active entities')
    expect(wrapper.text()).toContain('Updated quarterly')
    expect(wrapper.find('.kpi-detail__status').exists()).toBe(true)
  })

  it('exposes the external link action and a back-to-list action', () => {
    status.value = 'success'
    kpi.value = sampleKpi()
    const wrapper = mountView()
    const labels = wrapper.findAll('.btn__label').map((el) => el.text())
    expect(labels).toContain('Open the data source')
    expect(labels).toContain('Back to indicators')
  })

  it('renders a branded not-found state with a back link when the slug is unknown', () => {
    status.value = 'not-found'
    kpi.value = null
    const wrapper = mountView()
    expect(wrapper.find('.empty-state').exists()).toBe(true)
    expect(wrapper.text()).toContain('Indicator not found')
    expect(wrapper.find('.kpi-detail__body').exists()).toBe(false)
  })

  it('renders skeletons while loading and an error state with retry on failure', async () => {
    status.value = 'loading'
    kpi.value = null
    let wrapper = mountView()
    expect(wrapper.findAll('.skeleton').length).toBeGreaterThan(0)

    status.value = 'error'
    wrapper = mountView()
    load.mockClear()
    await wrapper.find('.error-state button').trigger('click')
    expect(load).toHaveBeenCalled()
  })
})
