import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AbandonedCarts from '@/views/settings/AbandonedCarts.vue'
import { messagingService } from '@/services/messagingService'
import { createTestGlobals } from '@/test-utils'

const posture = vi.hoisted(() => ({ showsModulesAwaitingBackend: true }))

vi.mock('@/constants/buildPosture', async (importOriginal) => ({
  ...(await importOriginal()),
  get SHOWS_MODULES_AWAITING_BACKEND() {
    return posture.showsModulesAwaitingBackend
  },
}))

vi.mock('@/services/messagingService', () => ({
  messagingService: { listAbandonedCarts: vi.fn(), sendCartCampaign: vi.fn() },
}))

function cart(id, marketingConsent) {
  return {
    id,
    entity: { name: { ar: 'جهة الاختبار', en: 'Test organisation' }, reference: `ENT-${id}` },
    email: `cart${id}@example.sa`,
    marketingConsent,
    purpose: 'accredited',
    startedAt: '2026-11-01T08:00:00Z',
    lastSeenAt: '2026-11-01T09:00:00Z',
    contactedAt: null,
  }
}

async function mountView(locale = 'ar') {
  const wrapper = mount(AbandonedCarts, {
    global: createTestGlobals({ locale }),
    attachTo: document.body,
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  messagingService.listAbandonedCarts.mockResolvedValue({
    data: [cart(1, true), cart(2, false)],
    meta: { currentPage: 1, lastPage: 1, perPage: 10, total: 2 },
  })
})

describe('Abandoned carts', () => {
  it('offers the campaign actions where the messaging module is served', async () => {
    posture.showsModulesAwaitingBackend = true
    const wrapper = await mountView()
    expect(wrapper.findAll('.dropdown-menu')).toHaveLength(1)
    expect(wrapper.find('.coming-soon-chip').exists()).toBe(false)
    expect(wrapper.text()).toContain('لا توجد موافقة تسويقية')
  })

  it.each([
    ['ar', 'قريباً'],
    ['en', 'Soon'],
  ])(
    'shows the coming-soon chip instead of mock campaigns against the API (%s)',
    async (locale, soon) => {
      posture.showsModulesAwaitingBackend = false
      const wrapper = await mountView(locale)
      expect(wrapper.find('.dropdown-menu').exists()).toBe(false)
      expect(wrapper.findAll('.coming-soon-chip')).toHaveLength(1)
      expect(wrapper.find('.coming-soon-chip').text()).toBe(soon)
      expect(messagingService.sendCartCampaign).not.toHaveBeenCalled()
    },
  )
})
