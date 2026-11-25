import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import StaffRecord from '@/views/assessment/admin/Record.vue'
import EntityAssessmentsTable from '@/components/entities/admin/EntityAssessmentsTable.vue'
import { assessmentsService } from '@/services/assessmentsService'
import { useAuthStore } from '@/stores/auth'
import { useTableColumnsStore } from '@/stores/tableColumns'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/assessmentsService', () => ({
  assessmentsService: { list: vi.fn(), get: vi.fn(), getResult: vi.fn() },
}))
vi.mock('@/services/accreditedService', () => ({
  accreditedService: { getCurrent: vi.fn().mockResolvedValue({ data: null }) },
}))

const FROZEN = {
  id: 9004,
  number: 'SLF-2026-000004',
  path: 'self',
  status: 'draft',
  isOpen: true,
  isFrozen: true,
  criteriaUpdateInProgress: false,
  scaleKind: 'of-10',
  entity: {
    id: 7002,
    reference: 'ENT-007002',
    name: { ar: 'مؤسسة إعمار للتنمية', en: 'Iemar Development Foundation' },
    status: 'active',
  },
  result: null,
  createdAt: '2026-10-12T08:45:00Z',
  submittedAt: null,
  completedAt: null,
}

async function mountAs(component, roles, props = {}, locale = 'en') {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  useAuthStore().setUser(sessionUserFor(roles))
  const wrapper = mount(component, { props, global: globals })
  await flushPromises()
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('staff assessment record', () => {
  it('shows a frozen request read-only with its badge, details, timeline and the placeholder', async () => {
    const statusHistory = [
      {
        from: null,
        to: 'draft',
        verb: 'start',
        at: '2026-10-12T08:45:00Z',
        actor: 'staff',
        actorStaff: {
          name: 'منيرة بنت خالد العتيبي',
          roles: [
            { key: 'system_admin', label: { ar: 'مدير النظام', en: 'System Administrator' } },
          ],
        },
      },
    ]
    assessmentsService.get.mockResolvedValue({ data: { ...FROZEN, statusHistory } })
    assessmentsService.getResult.mockResolvedValue({
      data: { id: 9004, status: 'draft', available: false, isFrozen: true },
    })
    const wrapper = await mountAs(StaffRecord, ['system_admin'], { id: '9004' })
    // The record has the request record's shape (design §B.3.4): the number as the title, then tabs.
    expect(wrapper.find('.request-detail__title').text()).toContain('SLF-2026-000004')
    expect(wrapper.find('.assessment-record__badges .frozen-badge').exists()).toBe(true)
    expect(wrapper.find('.assessment-notice--frozen').exists()).toBe(true)
    expect(wrapper.find('.assessment-record__details').text()).toContain('ENT-007002')
    expect(wrapper.find('.result-pending').exists()).toBe(true)
    expect(wrapper.find('a[href="/admin/entities/7002"]').exists()).toBe(true)
    expect(wrapper.find('input').exists()).toBe(false)
    await wrapper.findAll('.tabs__tab')[1].trigger('click')
    expect(wrapper.find('.assessment-timeline__actor').text()).toBe(
      'منيرة بنت خالد العتيبي — System Administrator',
    )
  })

  it('hides the entity link from a reader without entities.view and shows the missing state on 404', async () => {
    assessmentsService.get.mockRejectedValue({ normalized: { status: 404, message: 'Not found' } })
    assessmentsService.getResult.mockRejectedValue({
      normalized: { status: 404, message: 'Not found' },
    })
    const wrapper = await mountAs(StaffRecord, ['assessor'], { id: '9004' })
    expect(wrapper.find('.empty-state').exists()).toBe(true)
  })
})

describe('entity assessments table', () => {
  it('lists the entity assessments, frozen ones included, with links to the staff record', async () => {
    assessmentsService.list.mockResolvedValue({
      data: [FROZEN],
      meta: { pagination: { total: 1 } },
    })
    const wrapper = await mountAs(EntityAssessmentsTable, ['system_admin'], { entityId: 7002 })
    expect(assessmentsService.list).toHaveBeenCalledWith({
      'filter[entityId]': 7002,
      sort: '-created_at',
      page: 1,
      perPage: 15,
    })
    expect(wrapper.find('a[href="/admin/assessments/9004"]').text()).toBe('SLF-2026-000004')
    expect(wrapper.find('.frozen-badge').exists()).toBe(true)
  })

  it('shows the cycle, period, certificate and payment once the viewer adds them', async () => {
    assessmentsService.list.mockResolvedValueOnce({
      data: [
        {
          ...FROZEN,
          path: 'accredited',
          awardCycle: { id: 3, name: { ar: 'دورة 2026', en: '2026 cycle' } },
          periodLabel: { ar: 'النصف الثاني 2026', en: 'H2 2026' },
          level: 'gold',
          certificate: { number: 'CRT-2026-000001', validUntil: '2027-12-31' },
          payment: { status: 'paid', reference: 'PAY-2026-000012' },
        },
      ],
      meta: { pagination: { total: 1 } },
    })
    const wrapper = await mountAs(EntityAssessmentsTable, ['system_admin'], { entityId: 7002 })
    useTableColumnsStore().choices = {
      'entity-assessments': ['cycle', 'period', 'level', 'certificateNumber', 'payment'],
    }
    await flushPromises()
    const cells = wrapper.findAll('tbody td')
    expect(cells[1].text()).toBe('2026 cycle')
    expect(cells[2].text()).toBe('H2 2026')
    expect(cells[3].find('.level-badge').attributes('data-tier')).toBe('gold')
    expect(cells[4].text()).toBe('CRT-2026-000001')
    expect(cells[5].find('.badge').exists()).toBe(true)
  })

  it('shows the empty and error states', async () => {
    assessmentsService.list.mockResolvedValueOnce({ data: [], meta: {} })
    const empty = await mountAs(EntityAssessmentsTable, ['system_admin'], { entityId: 7002 })
    expect(empty.find('.empty-state').exists()).toBe(true)
    assessmentsService.list.mockRejectedValueOnce(new Error('down'))
    const failed = await mountAs(EntityAssessmentsTable, ['system_admin'], { entityId: 7002 })
    expect(failed.find('.error-state').exists()).toBe(true)
  })
})
