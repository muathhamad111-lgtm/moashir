import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import { contentService } from '@/services/contentService'
import { servicePricesService } from '@/services/servicePricesService'
import { kpisService } from '@/services/kpisService'
import { homePageFixture, siteSettingsFixture } from '@/mocks/fixtures/content'
import Home from '../Home.vue'
import {
  FEATURED,
  LEVELS,
  PRICES,
  stubBrowser,
} from '@/components/landing/__tests__/landingTestKit'

vi.mock('@/services/contentService', () => ({
  contentService: { getHome: vi.fn(), getCertificateLevels: vi.fn() },
}))
vi.mock('@/services/servicePricesService', () => ({
  servicePricesService: { getServicePrices: vi.fn() },
}))
vi.mock('@/services/kpisService', () => ({ kpisService: { getFeatured: vi.fn() } }))

const published = homePageFixture.sections.filter((section) => section.enabled)

async function mountHome() {
  const globals = createTestGlobals()
  await globals.router.push('/')
  const wrapper = mount(Home, { global: globals })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  stubBrowser()
  contentService.getCertificateLevels.mockResolvedValue({ data: { levels: LEVELS } })
  servicePricesService.getServicePrices.mockResolvedValue({ data: PRICES })
  kpisService.getFeatured.mockResolvedValue({ data: FEATURED, meta: { minimum: 3 } })
})

afterEach(() => vi.unstubAllGlobals())

describe('Home landing page', () => {
  it('renders the published sections in the CMS order with their anchors', async () => {
    contentService.getHome.mockResolvedValue({
      data: { published: true, sections: published, siteSettings: siteSettingsFixture },
    })
    const wrapper = await mountHome()
    const ids = wrapper
      .findAll('.lp-page > section, .lp-page > [id]')
      .map((node) => node.attributes('id'))
    expect(ids).toEqual([
      'hero',
      'stats',
      'about',
      'model',
      'paths',
      'journey',
      'begin',
      'levels',
      'kit',
      'lenses',
      'leaders',
      'start',
      'contact',
    ])
    expect(wrapper.find('h1').exists()).toBe(true)
  })

  it('follows a reordered and partly hidden CMS page', async () => {
    const reordered = [published[4], published[0]]
    contentService.getHome.mockResolvedValue({
      data: { published: true, sections: reordered, siteSettings: siteSettingsFixture },
    })
    const wrapper = await mountHome()
    const ids = wrapper.findAll('.lp-page > [id]').map((node) => node.attributes('id'))
    expect(ids).toEqual(['paths', 'hero'])
  })

  it('shows a retry action when the content cannot be loaded', async () => {
    contentService.getHome.mockRejectedValueOnce(new Error('offline'))
    const wrapper = await mountHome()
    expect(wrapper.find('.lp-state').text()).toContain('تعذّر تحميل الصفحة')
    contentService.getHome.mockResolvedValue({
      data: { published: true, sections: published, siteSettings: siteSettingsFixture },
    })
    await wrapper.find('.lp-state button').trigger('click')
    await flushPromises()
    expect(wrapper.find('#hero').exists()).toBe(true)
  })

  it('tells visitors when the page is not published', async () => {
    contentService.getHome.mockResolvedValue({ data: { published: false, maintenance: true } })
    const wrapper = await mountHome()
    expect(wrapper.find('.lp-state').exists()).toBe(true)
    expect(wrapper.find('.lp-state button').exists()).toBe(false)
  })
})
