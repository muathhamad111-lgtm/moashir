import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import BagManageHeader from '@/components/bag/BagManageHeader.vue'
import { createTestGlobals } from '@/test-utils'
import { bagRequestsService } from '@/services/bagRequestsService'
import { ROUTE_NAMES } from '@/constants/routeNames'

vi.mock('@/services/bagRequestsService', () => ({ bagRequestsService: { get: vi.fn() } }))

const REQUEST = {
  id: 'bag-req-7003',
  entityName: { ar: 'وقف الخير للرعاية الصحية', en: 'Al-Khair Waqf for Healthcare' },
  status: 'assigned',
  referenceSnapshot: { assessmentId: 9212, version: 1, scorePercent: 58 },
}

describe('BagManageHeader', () => {
  it('names the Kit request the plan belongs to and links back to the list', async () => {
    bagRequestsService.get.mockResolvedValue({ data: REQUEST })
    const globals = createTestGlobals({ locale: 'ar' })
    const wrapper = mount(BagManageHeader, {
      props: { requestId: 'bag-req-7003' },
      global: globals,
    })
    await flushPromises()
    expect(bagRequestsService.get).toHaveBeenCalledWith('bag-req-7003')
    const request = wrapper.find('[data-testid="bag-manage-request"]')
    expect(request.text()).toContain('وقف الخير للرعاية الصحية')
    expect(request.text()).toContain('معتمد')
    expect(request.find('.badge').exists()).toBe(true)
    expect(wrapper.find('.bag-manage__back').attributes('href')).toBe(
      globals.router.resolve({ name: ROUTE_NAMES.BAG_EVALUATOR_REQUESTS }).href,
    )
  })

  it('keeps the title when the request cannot be read', async () => {
    bagRequestsService.get.mockRejectedValue(new Error('down'))
    const wrapper = mount(BagManageHeader, {
      props: { requestId: 'bag-req-0' },
      global: createTestGlobals({ locale: 'en' }),
    })
    await flushPromises()
    expect(wrapper.find('.bag-manage__title').exists()).toBe(true)
    expect(wrapper.find('[data-testid="bag-manage-request"]').exists()).toBe(false)
  })

  it('sends a Kit plan without a request to the assessor Kit requests', async () => {
    const { router } = createTestGlobals()
    expect(router.resolve('/bag/manage').name).toBeUndefined()
    await router.push('/bag/manage')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.BAG_EVALUATOR_REQUESTS)
  })
})
