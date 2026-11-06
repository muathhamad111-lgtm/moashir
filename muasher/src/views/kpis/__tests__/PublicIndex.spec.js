import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import PublicIndex from '@/views/kpis/PublicIndex.vue'
import { kpisService } from '@/services/kpisService'
import { contentService } from '@/services/contentService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/kpisService', () => ({
  kpisService: { list: vi.fn(), listCategories: vi.fn(), listTypes: vi.fn() },
}))
vi.mock('@/services/contentService', () => ({ contentService: { getPage: vi.fn() } }))

const CATEGORIES = [
  { id: 1, key: 'beneficiaries', name: { ar: 'المستفيدون', en: 'Beneficiaries' } },
  { id: 2, key: 'performance', name: { ar: 'الأداء', en: 'Performance' } },
]

function makeKpi(id, nameEn, status = 'good') {
  return {
    id,
    slug: `kpi-${id}`,
    name: { ar: `مؤشر ${id}`, en: nameEn },
    value: String(100 + id),
    target: '300',
    unit: { ar: 'وحدة', en: 'unit' },
    status,
    cardColor: 'primary',
  }
}

function page(items, current = 1, lastPage = 1) {
  return {
    data: items,
    meta: { pagination: { page: current, perPage: 12, total: items.length, lastPage } },
  }
}

async function mountView() {
  const wrapper = mount(PublicIndex, { global: createTestGlobals({ locale: 'en' }) })
  await flushPromises()
  return wrapper
}

async function settle() {
  await vi.advanceTimersByTimeAsync(400)
  await flushPromises()
}

beforeEach(() => {
  vi.useFakeTimers()
  vi.clearAllMocks()
  kpisService.listCategories.mockResolvedValue({ data: CATEGORIES })
  kpisService.listTypes.mockResolvedValue({
    data: [{ id: 5, key: 'output', name: { ar: 'مخرجات', en: 'Output' } }],
  })
  contentService.getPage.mockResolvedValue({
    data: {
      key: 'kpis',
      sections: [
        {
          id: 1,
          type: 'hero',
          title: { ar: 'مؤشرات', en: 'Excellence indicators' },
          description: { ar: 'وصف', en: 'Up-to-date indicators' },
        },
      ],
    },
  })
  kpisService.list.mockResolvedValue(
    page([makeKpi(1, 'Participating associations'), makeKpi(2, 'Volunteers', 'excellent')], 1, 2),
  )
})

afterEach(() => vi.useRealTimers())

describe('KPI public index (server-driven catalog)', () => {
  it('renders the first page, the category tabs and the CMS hero copy', async () => {
    const wrapper = await mountView()
    expect(kpisService.list).toHaveBeenCalledWith({ page: 1, perPage: 12 })
    expect(wrapper.findAll('.kpi-card')).toHaveLength(2)
    expect(wrapper.findAll('.tabs__tab').map((tab) => tab.text())).toEqual([
      'All',
      'Beneficiaries',
      'Performance',
    ])
    expect(wrapper.find('.kpis-page__title').text()).toBe('Excellence indicators')
  })

  it('asks the server for the selected category, status and minimum-length search', async () => {
    const wrapper = await mountView()
    await wrapper.findAll('.tabs__tab')[1].trigger('click')
    await settle()
    expect(kpisService.list).toHaveBeenLastCalledWith({
      'filter[categoryId]': 1,
      page: 1,
      perPage: 12,
    })
    await wrapper.find('.toolbar__search input').setValue('V')
    await settle()
    expect(kpisService.list.mock.lastCall[0]).not.toHaveProperty('search')
    await wrapper.find('.toolbar__search input').setValue('Vol')
    await settle()
    expect(kpisService.list.mock.lastCall[0]).toMatchObject({
      search: 'Vol',
      'filter[categoryId]': 1,
    })
  })

  it('appends the next page on show more', async () => {
    const wrapper = await mountView()
    kpisService.list.mockResolvedValueOnce(page([makeKpi(3, 'Employees')], 2, 2))
    await wrapper.find('.kpis-page__more button').trigger('click')
    await flushPromises()
    expect(kpisService.list).toHaveBeenLastCalledWith({ page: 2, perPage: 12 })
    expect(wrapper.findAll('.kpi-card')).toHaveLength(3)
    expect(wrapper.find('.kpis-page__more').exists()).toBe(false)
  })

  it('shows the no-results state when the filtered page is empty', async () => {
    const wrapper = await mountView()
    kpisService.list.mockResolvedValue(page([]))
    await wrapper.find('.toolbar__search input').setValue('Nothing here')
    await settle()
    expect(wrapper.findAll('.kpi-card')).toHaveLength(0)
    expect(wrapper.text()).toContain('No matching results')
  })

  it('shows the empty state without filters and an error state with retry', async () => {
    kpisService.list.mockResolvedValueOnce(page([]))
    let wrapper = await mountView()
    expect(wrapper.text()).toContain('No indicators published yet')
    kpisService.list.mockRejectedValueOnce(new Error('down'))
    wrapper = await mountView()
    expect(wrapper.find('.error-state').exists()).toBe(true)
    await wrapper.find('.error-state button').trigger('click')
    await flushPromises()
    expect(wrapper.findAll('.kpi-card')).toHaveLength(2)
  })
})
