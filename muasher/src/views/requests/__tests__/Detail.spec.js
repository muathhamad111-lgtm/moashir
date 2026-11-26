import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import Detail from '@/views/requests/Detail.vue'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'
import { useAuthStore } from '@/stores/auth'

const service = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@/services/requestsService', () => ({ requestsService: service }))

// The staff request record (request page v4.1, layout A): the summary card, the result ribbon once completed with a
// level, then only the tabs that apply, in the order staff open them.
function detail(overrides = {}) {
  return {
    id: 9108,
    number: 'ACC-2026-000008',
    path: 'accredited',
    pathLabel: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
    status: 'under-review',
    isFrozen: false,
    entity: { id: 6002, name: { en: 'Ehsan Society' }, reference: 'ENT-006002', logo: null },
    assessor: { id: 10, name: 'Dr. Sultan' },
    isReassigned: false,
    assignedAt: '2026-10-26T11:00:00Z',
    submittedAt: '2026-10-25T08:00:00Z',
    dueAt: '2026-11-08T08:00:00Z',
    isOverdue: false,
    completedAt: null,
    completionDeadline: null,
    hasCertificates: false,
    record: { level: null, certificate: null, result: null, statusHistory: [] },
    assignmentHistory: [],
    completionRequests: [],
    payment: { paid: true, details: { reference: 'PAY-2026-000019', status: 'paid' } },
    ...overrides,
  }
}

async function mountAt(path, data) {
  service.get.mockResolvedValue({ data })
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(['system_admin']))
  await globals.router.push(path)
  await globals.router.isReady()
  const wrapper = mount(Detail, {
    global: {
      plugins: globals.plugins,
      directives: globals.directives,
      stubs: {
        RequestCertificateCard: true,
        RequestResultBreakdown: true,
        RequestLogTab: true,
        RequestRecordDialogs: true,
        RequestPaymentCard: true,
      },
    },
  })
  await flushPromises()
  return { wrapper, router: globals.router }
}

function tabLabels(wrapper) {
  return wrapper.findAll('[role="tab"]').map((tab) => tab.text())
}

beforeEach(() => vi.clearAllMocks())

describe('request record page', () => {
  it('shows the summary card and the tabs of a paid request under review', async () => {
    const { wrapper } = await mountAt('/admin/requests/9108', detail())
    expect(service.get).toHaveBeenCalledWith(9108)
    expect(wrapper.find('.record-summary h1').text()).toBe('ACC-2026-000008')
    expect(wrapper.find('[data-testid="level-ribbon"]').exists()).toBe(false)
    expect(tabLabels(wrapper)).toEqual(['Overview', 'Payment', 'History'])
  })

  it('shows the result ribbon and the assessment and certificate tabs once completed', async () => {
    const completed = detail({
      status: 'completed',
      completedAt: '2026-11-01T08:00:00Z',
      hasCertificates: true,
      record: {
        level: 'silver',
        certificate: { number: 'CRT-2026-000003' },
        result: { percent: 74.54 },
        statusHistory: [],
      },
    })
    const { wrapper } = await mountAt('/admin/requests/9108', completed)
    expect(wrapper.find('[data-testid="level-ribbon"]').text()).toContain('CRT-2026-000003')
    expect(tabLabels(wrapper)).toEqual([
      'Overview',
      'Assessment',
      'Certificate',
      'Payment',
      'History',
    ])
  })

  it('keeps the certificate tab of a reopened request that has earlier certificates', async () => {
    const reopened = await mountAt('/admin/requests/9108', detail({ hasCertificates: true }))
    expect(tabLabels(reopened.wrapper)).toContain('Certificate')
    const fresh = await mountAt('/admin/requests/9108', detail())
    expect(tabLabels(fresh.wrapper)).not.toContain('Certificate')
  })

  it('opens the tab named in the address and keeps the choice there', async () => {
    const { wrapper, router } = await mountAt('/admin/requests/9108?tab=payment', detail())
    expect(wrapper.find('[role="tab"][aria-selected="true"]').text()).toBe('Payment')
    await wrapper.findAll('[role="tab"]').at(2).trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.tab).toBe('log')
  })
})
