import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import { accreditedService } from '@/services/accreditedService'
import { servicePricesService } from '@/services/servicePricesService'
import { contentService } from '@/services/contentService'
import Intro from '../Intro.vue'

vi.mock('@/services/accreditedService', () => ({
  accreditedService: { getResult: vi.fn(), getCurrent: vi.fn() },
}))
vi.mock('@/services/servicePricesService', () => ({
  servicePricesService: { getServicePrices: vi.fn() },
}))
vi.mock('@/services/contentService', () => ({ contentService: { getPage: vi.fn() } }))

const PRICE = {
  priceBeforeVat: '1000.00',
  vatRate: '15.00',
  vatAmount: '150.00',
  total: '1150.00',
  currency: 'SAR',
}

async function mountAt(component, path, roles) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  if (roles) useAuthStore(globals.pinia).setUser(sessionUserFor(roles))
  await globals.router.push(path)
  const wrapper = mount(component, {
    global: { plugins: globals.plugins, stubs: { teleport: true } },
  })
  await flushPromises()
  return { wrapper, globals }
}

beforeEach(() => {
  vi.clearAllMocks()
  contentService.getPage.mockRejectedValue(
    Object.assign(new Error('not published'), { response: { status: 404 } }),
  )
  servicePricesService.getServicePrices.mockResolvedValue({
    data: { accredited: PRICE, bag: null },
  })
})

describe('accredited views', () => {
  it('shows the price to a guest with a sign-in action and the open request to an entity', async () => {
    const guest = await mountAt(Intro, '/assessment/accredited')
    expect(guest.wrapper.findAll('.pricing-breakdown__row')).toHaveLength(3)
    expect(guest.wrapper.find('.accredited-price__action').text()).toBe('Sign in to start')
    accreditedService.getCurrent.mockResolvedValue({
      data: {
        awaitingPayment: null,
        active: { id: 9101, number: 'ACC-2026-000004', status: 'draft' },
        latestClosed: null,
      },
    })
    const entity = await mountAt(Intro, '/assessment/accredited', ['entity'])
    expect(entity.wrapper.find('.accredited-price__action').text()).toBe('Open the current request')
    expect(entity.wrapper.find('.accredited-current').text()).toContain('ACC-2026-000004')
  })

  it('shows the accredited_intro CMS page copy in the intro slots', async () => {
    contentService.getPage.mockResolvedValue({
      data: {
        key: 'accredited_intro',
        sections: [
          {
            id: 1,
            type: 'rich_text',
            title: { en: 'Accredited assessment' },
            subtitle: { en: 'A trusted evidence-based score' },
            description: { en: 'Assessors review the evidence.' },
            items: [],
          },
          {
            id: 2,
            type: 'faq',
            title: { en: 'Why we recommend it' },
            description: { en: 'An accurate reading of maturity.' },
            items: [
              { id: 21, title: { en: 'Expert review' }, description: { en: 'Objective reading' } },
            ],
          },
          {
            id: 3,
            type: 'pricing_box',
            title: { en: 'Fees and tax' },
            description: { en: 'Shown before VAT.' },
            items: [],
          },
        ],
      },
    })
    const { wrapper } = await mountAt(Intro, '/assessment/accredited')
    expect(contentService.getPage).toHaveBeenCalledWith('accredited_intro')
    expect(wrapper.find('.accredited-intro__subtitle').text()).toBe(
      'A trusted evidence-based score',
    )
    expect(wrapper.text()).toContain('Assessors review the evidence.')
    expect(wrapper.text()).toContain('Why we recommend it')
    expect(wrapper.find('.accredited-intro__benefit-title').text()).toBe('Expert review')
    expect(wrapper.text()).toContain('Fees and tax')
  })

  it('titles the price card "Complete payment" and goes straight to the amounts when the page has no fee copy', async () => {
    contentService.getPage.mockResolvedValue({ data: { key: 'accredited_intro', sections: [] } })
    const { wrapper } = await mountAt(Intro, '/assessment/accredited')
    expect(wrapper.find('.accredited-price__title').text()).toBe('Complete payment')
    expect(wrapper.find('.accredited-price__description').exists()).toBe(false)
    expect(wrapper.findAll('.pricing-breakdown__row')).toHaveLength(3)
  })
})
