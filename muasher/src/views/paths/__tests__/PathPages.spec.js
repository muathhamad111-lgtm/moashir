import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import AccreditedPath from '@/views/paths/AccreditedPath.vue'
import AwardPath from '@/views/paths/AwardPath.vue'
import AccreditedRequest from '@/views/paths/AccreditedRequest.vue'
import { dashboardService } from '@/services/dashboardService'
import { accreditedService } from '@/services/accreditedService'
import { pathsService } from '@/services/pathsService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/dashboardService', () => ({ dashboardService: { getEntityBoard: vi.fn() } }))
vi.mock('@/services/accreditedService', () => ({
  accreditedService: { getCurrent: vi.fn(), getResult: vi.fn(), startFree: vi.fn() },
}))
vi.mock('@/services/pathsService', () => ({
  pathsService: { listRequests: vi.fn(), getRequest: vi.fn() },
}))
vi.mock('@/services/assessmentsService', () => ({ assessmentsService: { get: vi.fn() } }))

const Q4 = {
  key: '2026-Q4',
  label: { ar: 'الربع الرابع 2026', en: 'Q4 2026' },
  startsOn: '2026-10-01',
  endsOn: '2026-12-31',
}
const PRICE = {
  priceBeforeVat: '1000.00',
  vatRate: '15.00',
  vatAmount: '150.00',
  total: '1150.00',
  currency: 'SAR',
}

function board(accredited = {}, award = {}) {
  return {
    paths: {
      self: null,
      accredited: {
        pricing: { price: PRICE, isFree: false, availability: 'available' },
        isLive: true,
        cadence: 'quarterly',
        currentPeriod: Q4,
        periodStatus: 'assessed',
        nextPeriod: {
          ...Q4,
          key: '2027-Q1',
          label: { ar: 'الربع الأول 2027', en: 'Q1 2027' },
          noticeOn: '2026-12-18',
        },
        nextSubmissionFrom: '2027-01-01',
        awaitingPayment: null,
        openRequest: null,
        lastResult: { assessmentId: 9102, number: 'ACC-2026-000001' },
        kit: { status: 'offered', requestId: null },
        ...accredited,
      },
      award: {
        pricing: { price: null, isFree: true, availability: 'available' },
        windowState: 'closed',
        cycle: {
          id: 3,
          name: { ar: 'جائزة التميز 2026', en: 'Excellence Award 2026' },
          submissionStart: '2026-09-01T21:00:00+00:00',
          submissionEnd: '2026-10-15T20:59:59+00:00',
          regions: [],
          minEntityAgeYears: 2,
        },
        nextCycle: { id: 4, name: null, submissionStart: '2027-09-01T21:00:00+00:00' },
        eligibility: { eligible: false, reason: 'window_closed' },
        openRequest: null,
        announced: false,
        lastResult: null,
        ...award,
      },
    },
  }
}

const MISSED = {
  rowType: 'missed_period',
  rowKey: 'period-20262',
  id: null,
  number: '',
  status: null,
  periodStatus: 'missed',
  periodLabel: { ar: 'الربع الثاني 2026', en: 'Q2 2026' },
}

async function mountAt(component, path, locale = 'ar') {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(['entity']))
  await globals.router.push(path)
  const wrapper = mount(component, { global: { ...globals, stubs: { teleport: true } } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  dashboardService.getEntityBoard.mockResolvedValue({ data: board() })
  accreditedService.getCurrent.mockResolvedValue({
    data: { apply: { canStart: true, reason: null, warning: 'period_already_assessed' } },
  })
  pathsService.listRequests.mockResolvedValue({
    data: [MISSED],
    meta: { pagination: { page: 1, lastPage: 1, total: 1 } },
  })
})

describe('accredited path page', () => {
  it('shows the period card, the assessed state, the next period and a missed row', async () => {
    const wrapper = await mountAt(AccreditedPath, '/paths/accredited')
    expect(pathsService.listRequests).toHaveBeenCalledWith('accredited', { page: 1, perPage: 10 })
    expect(wrapper.find('[data-testid="path-period-card"]').text()).toContain('الربع الرابع 2026')
    expect(wrapper.find('[data-testid="path-period-status"]').text()).toBe('أنجزت تقييم هذه الفترة')
    expect(wrapper.find('[data-testid="path-status-card"]').text()).toContain('2027-01-01')
    expect(wrapper.find('[data-testid="path-apply-next"]').exists()).toBe(true)
    const row = wrapper.find('[data-testid="path-row-period-20262"]')
    expect(row.text()).toContain('الربع الثاني 2026')
    expect(row.text()).toContain('لم يُقدَّم')
    expect(row.find('a').exists()).toBe(false)
  })

  it('shows «غير متاح حالياً» when the path is paused', async () => {
    dashboardService.getEntityBoard.mockResolvedValue({
      data: board({
        periodStatus: 'not-submitted',
        pricing: { price: null, isFree: false, availability: 'paused' },
      }),
    })
    const wrapper = await mountAt(AccreditedPath, '/paths/accredited', 'en')
    expect(wrapper.find('[data-testid="path-status-card"]').text()).toContain(
      'Currently unavailable',
    )
    expect(wrapper.find('[data-testid="path-primary-action"]').exists()).toBe(false)
  })
})

describe('award path page', () => {
  it('shows the closed window with the next cycle date and no apply action', async () => {
    pathsService.listRequests.mockResolvedValue({ data: [], meta: {} })
    const wrapper = await mountAt(AwardPath, '/paths/award')
    expect(pathsService.listRequests).toHaveBeenCalledWith('award', { page: 1, perPage: 10 })
    expect(wrapper.find('[data-testid="path-cycle-card"]').text()).toContain('انتهت نافذة التقديم')
    expect(wrapper.find('[data-testid="path-next-cycle"]').text()).toContain('2027-09-02')
    expect(wrapper.find('[data-testid="path-primary-action"]').exists()).toBe(false)
  })
})

describe('Task-80 path states', () => {
  it('starts a free accredited request in place and lands on its form', async () => {
    dashboardService.getEntityBoard.mockResolvedValue({
      data: board({
        periodStatus: 'not-submitted',
        lastResult: null,
        pricing: { price: null, isFree: true, availability: 'available' },
      }),
    })
    accreditedService.startFree.mockResolvedValue({ data: { id: 9301, number: 'ACC-2026-000301' } })
    const wrapper = await mountAt(AccreditedPath, '/paths/accredited')
    expect(wrapper.find('[data-testid="path-price"]').text()).toContain('مجاني')
    const action = wrapper.find('[data-testid="path-primary-action"]')
    expect(action.element.tagName).toBe('BUTTON')
    const push = vi.spyOn(wrapper.vm.$router, 'push')
    await action.trigger('click')
    await flushPromises()
    expect(accreditedService.startFree).toHaveBeenCalledTimes(1)
    expect(push).toHaveBeenCalledWith({
      name: 'assessment.accredited.form',
      params: { id: '9301' },
    })
  })

  it('sends a changed price to the checkout when the free start answers payment required', async () => {
    dashboardService.getEntityBoard.mockResolvedValue({
      data: board({
        periodStatus: 'not-submitted',
        pricing: { price: null, isFree: true, availability: 'available' },
      }),
    })
    accreditedService.startFree.mockRejectedValue({
      normalized: { status: 409, code: 'path.payment_required', meta: {} },
    })
    const wrapper = await mountAt(AccreditedPath, '/paths/accredited')
    const push = vi.spyOn(wrapper.vm.$router, 'push')
    await wrapper.find('[data-testid="path-primary-action"]').trigger('click')
    await flushPromises()
    expect(push).toHaveBeenCalledWith({ name: 'assessment.accredited.pay' })
  })

  it('shows «انتهت نافذة التقديم قبل اعتماد التحويل» without a continue action', async () => {
    dashboardService.getEntityBoard.mockResolvedValue({
      data: board(
        {},
        {
          openRequest: { id: 77, number: 'ADV-2026-00077', status: 'draft', isOpen: true },
          windowClosedBeforePayment: true,
        },
      ),
    })
    pathsService.listRequests.mockResolvedValue({
      data: [
        {
          id: 77,
          rowKey: 'request-77',
          number: 'ADV-2026-00077',
          status: 'draft',
          isOpen: true,
          awardCycle: { id: 3, name: { ar: 'جائزة التميز 2026', en: 'Excellence Award 2026' } },
        },
      ],
      meta: { pagination: { page: 1, lastPage: 1, total: 1 } },
    })
    const wrapper = await mountAt(AwardPath, '/paths/award')
    const card = wrapper.find('[data-testid="path-status-card"]')
    expect(card.attributes('data-state')).toBe('latePayment')
    expect(card.text()).toContain('انتهت نافذة التقديم قبل اعتماد التحويل')
    expect(wrapper.find('[data-testid="path-late-payment"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="path-primary-action"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="path-row-request-77"]').text()).toContain(
      'جائزة التميز 2026',
    )
  })

  it('shows «غير متاح حالياً» on a path that is not listed', async () => {
    dashboardService.getEntityBoard.mockResolvedValue({
      data: board(
        {},
        { isListed: false, pricing: { price: null, isFree: true, availability: 'hidden' } },
      ),
    })
    pathsService.listRequests.mockResolvedValue({ data: [], meta: {} })
    const wrapper = await mountAt(AwardPath, '/paths/award')
    expect(wrapper.find('[data-testid="path-unavailable-card"]').text()).toContain(
      'غير متاح حالياً',
    )
    expect(wrapper.find('[data-testid="path-cycle-card"]').exists()).toBe(false)
  })
})

describe('accredited request detail', () => {
  const RECORD = {
    id: 9102,
    number: 'ACC-2026-000001',
    status: 'completed',
    periodLabel: { ar: 'الربع الثالث 2026', en: 'Q3 2026' },
    submittedAt: '2026-08-20T09:00:00Z',
    completedAt: '2026-09-10T12:00:00Z',
    payment: null,
    level: 'silver',
    certificate: {
      number: 'CRT-2026-000001',
      level: 'silver',
      status: 'issued',
      issuedAt: '2026-09-10T12:00:00Z',
      validUntil: '2026-12-31',
      isExpired: false,
    },
    kit: { status: 'offered', requestId: null },
    availableTabs: ['request', 'result', 'feedback', 'certificate', 'tools'],
    defaultTab: 'result',
    statusHistory: [],
  }

  beforeEach(() => {
    pathsService.getRequest.mockResolvedValue({ data: RECORD })
    accreditedService.getResult.mockResolvedValue({
      data: { available: true, percent: '82.93', main: [], sub: [], points: [], feedback: [] },
    })
  })

  it('shows only the server tabs, opens the default tab and offers the Kit', async () => {
    const wrapper = await mountAt(AccreditedRequest, '/paths/accredited/9102')
    const tabs = wrapper.findAll('[role="tab"]').map((tab) => tab.text())
    expect(tabs).toEqual(['الطلب', 'النتيجة', 'التقرير التعقيبي', 'الشهادة', 'أدوات التميّز'])
    expect(wrapper.find('[role="tab"][aria-selected="true"]').text()).toBe('النتيجة')
    expect(accreditedService.getResult).toHaveBeenCalledWith(9102)
    expect(wrapper.find('[data-testid="kit-offer"]').exists()).toBe(true)
  })

  it('opens the certificate tab from the query with its validity date', async () => {
    const wrapper = await mountAt(AccreditedRequest, '/paths/accredited/9102?tab=certificate', 'en')
    expect(wrapper.find('[data-testid="certificate-valid-until"]').text()).toContain('2026-12-31')
  })

  it('falls back to the default tab when the query names an unavailable one', async () => {
    const wrapper = await mountAt(AccreditedRequest, '/paths/accredited/9102?tab=kit')
    expect(wrapper.find('[role="tab"][aria-selected="true"]').text()).toBe('النتيجة')
  })
})
