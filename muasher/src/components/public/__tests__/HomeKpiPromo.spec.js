import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import HomeKpiPromo from '@/components/public/HomeKpiPromo.vue'
import { createTestGlobals } from '@/test-utils'

const getFeatured = vi.fn()

vi.mock('@/services/kpisService', () => ({
  kpisService: {
    getFeatured: (...args) => getFeatured(...args),
  },
}))

const section = {
  id: 'sec-kpi-promo',
  key: 'kpi-promo',
  icon: 'fa-solid fa-chart-column',
  title: { ar: 'مؤشرات الأداء الرئيسية', en: 'Key performance indicators' },
  description: { ar: 'وصف', en: 'A dedicated indicators page' },
  ctaLabel: { ar: 'استعرض جميع المؤشرات', en: 'Explore performance indicators' },
  ctaUrl: '/kpis',
  items: [],
}

function localized(content) {
  return typeof content === 'string' ? content : content.en
}

function makeKpi(id, nameEn) {
  return {
    id,
    slug: `kpi-${id}`,
    name: { ar: `مؤشر ${id}`, en: nameEn },
    value: 100 + id,
    target: 300,
    baseline: 50,
    unit: { ar: 'وحدة', en: 'unit' },
    description: { ar: 'وصف', en: 'desc' },
    category: 'performance',
    status: 'good',
    lastUpdate: '2025-01-01',
    icon: 'fa-solid fa-chart-line',
    color: 'primary',
    cardColor: 'primary',
    showOnHomepage: true,
    order: id,
    published: true,
  }
}

function mountPromo() {
  return mount(HomeKpiPromo, {
    props: { section, localized },
    global: createTestGlobals({ locale: 'en' }),
  })
}

beforeEach(() => {
  getFeatured.mockReset()
})

describe('HomeKpiPromo featured preview', () => {
  it('renders a KpiCard for each featured indicator plus the view-all CTA', async () => {
    getFeatured.mockResolvedValue({
      data: [makeKpi(1, 'Participating associations'), makeKpi(2, 'Beneficiaries')],
    })
    const wrapper = mountPromo()
    await flushPromises()

    expect(getFeatured).toHaveBeenCalled()
    expect(wrapper.findAll('.kpi-card')).toHaveLength(2)
    expect(wrapper.find('.home-kpi-promo__preview').exists()).toBe(true)
    expect(wrapper.find('.home-kpi-promo__cta .btn__label').text()).toBe(
      'Explore performance indicators',
    )
  })

  it('renders only the CTA (no broken grid) when no indicators are featured', async () => {
    getFeatured.mockResolvedValue({ data: [] })
    const wrapper = mountPromo()
    await flushPromises()

    expect(wrapper.find('.home-kpi-promo__preview').exists()).toBe(false)
    expect(wrapper.findAll('.kpi-card')).toHaveLength(0)
    expect(wrapper.find('.home-kpi-promo__cta').exists()).toBe(true)
  })

  it('stays graceful (CTA only) when the featured request fails', async () => {
    getFeatured.mockRejectedValue(new Error('down'))
    const wrapper = mountPromo()
    await flushPromises()

    expect(wrapper.find('.home-kpi-promo__preview').exists()).toBe(false)
    expect(wrapper.find('.home-kpi-promo__cta').exists()).toBe(true)
  })
})
