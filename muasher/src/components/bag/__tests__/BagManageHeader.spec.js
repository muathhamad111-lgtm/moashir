import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import BagManageHeader from '@/components/bag/BagManageHeader.vue'
import { createTestGlobals } from '@/test-utils'
import { bagRequestsService } from '@/services/bagRequestsService'
import { ROUTE_NAMES } from '@/constants/routeNames'

vi.mock('@/services/bagRequestsService', () => ({ bagRequestsService: { get: vi.fn() } }))

const REQUEST = {
  id: 'bag-req-7003',
  number: 'KIT-2026-000004',
  paidAt: '2026-07-06T12:00:00+03:00',
  assignedAt: '2026-07-07T10:00:00+03:00',
  assignedEvaluatorName: 'د. سلطان',
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
    // Request page v4.1: the Kit is titled by its number, its reference result is a line under it, the status sits
    // once at the end, and the evaluator and the dates are two titled groups.
    expect(wrapper.find('h1').text()).toBe('KIT-2026-000004')
    expect(wrapper.find('.record-identity__kind').text()).toBe('حقيبة التميّز')
    expect(wrapper.find('.record-identity__subject').text()).toContain('معتمد')
    expect(wrapper.findAll('.record-status__badge')).toHaveLength(1)
    expect(wrapper.find('.record-status__since').exists()).toBe(true)
    expect(wrapper.findAll('.record-group h2').map((title) => title.text())).toEqual([
      'الإسناد',
      'المواعيد',
    ])
    expect(wrapper.find('.record-facts__value').text()).toBe('د. سلطان')
    expect(wrapper.find('.record-summary__back').attributes('href')).toBe(
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
    expect(wrapper.find('h1').text()).toBe('Manage the Quick Excellence Kit')
    expect(wrapper.find('[data-testid="bag-manage-request"]').exists()).toBe(false)
    expect(wrapper.find('.record-status').exists()).toBe(false)
  })

  it('sends a Kit plan without a request to the assessor Kit requests', async () => {
    const { router } = createTestGlobals()
    expect(router.resolve('/bag/manage').name).toBeUndefined()
    await router.push('/bag/manage')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.BAG_EVALUATOR_REQUESTS)
  })
})
