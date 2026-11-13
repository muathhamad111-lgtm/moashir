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
    shortDescription: { ar: 'وصف', en: 'Registered associations on the platform' },
    fullDescription: {
      ar: 'وصف كامل',
      en: 'All associations registered and active on the platform',
    },
    value: '486',
    target: '700',
    baseline: '320',
    unit: { ar: 'جمعية', en: 'associations' },
    category: { id: 3, key: 'beneficiaries', name: { ar: 'المستفيدون', en: 'Beneficiaries' } },
    type: { id: 2, key: 'output', name: { ar: 'مؤشر مخرجات', en: 'Output indicator' } },
    status: 'good',
    owner: { ar: 'الإدارة', en: 'Registration Department' },
    dataSource: { ar: 'السجل', en: 'Platform log' },
    measurementYear: 2026,
    lastUpdate: '2026-06-30T09:00:00Z',
    createdAt: '2026-01-10T09:00:00Z',
    externalLink: 'https://data.moashir.example.sa/associations',
    calcMethod: { ar: 'طريقة', en: 'Count of active entities' },
    notes: { ar: 'ملاحظات', en: 'Updated quarterly' },
    icon: 'users-two',
    image: null,
    cardColor: 'success',
    badgeColor: 'info',
    history: [
      { value: '320', measuredOn: '2024-12-31' },
      { value: '486', measuredOn: '2026-06-30' },
    ],
    seo: {
      title: { ar: 'الجمعيات المشاركة', en: 'Participating associations' },
      description: null,
    },
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
    expect(wrapper.find('.kpi-detail__body').classes()).toContain('kpi-detail__body--success')
  })

  it('lists the facts with category and type names and the value history newest first', () => {
    status.value = 'success'
    kpi.value = sampleKpi()
    const wrapper = mountView()
    const facts = wrapper.findAll('.kpi-detail__fact-value').map((el) => el.text())
    expect(facts).toContain('Beneficiaries')
    expect(facts).toContain('Output indicator')
    expect(facts).toContain('2026')
    const rows = wrapper.findAll('.kpi-history__row')
    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('486')
    expect(wrapper.find('progress.kpi-detail__meter').attributes('value')).toBe('69')
  })

  it('exposes the external link action and a back-to-list action', () => {
    status.value = 'success'
    kpi.value = sampleKpi()
    const wrapper = mountView()
    const external = wrapper.find('.kpi-detail__actions a[target="_blank"]')
    expect(external.attributes('href')).toBe('https://data.moashir.example.sa/associations')
    expect(external.attributes('rel')).toContain('noopener')
    expect(external.text()).toContain('Open the data source')
    expect(wrapper.findAll('.btn__label').map((el) => el.text())).toContain('Back to indicators')
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
