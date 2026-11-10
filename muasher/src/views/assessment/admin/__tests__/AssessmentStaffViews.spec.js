import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import StaffRecord from '@/views/assessment/admin/Record.vue'
import EntityAssessmentsCard from '@/components/entities/admin/EntityAssessmentsCard.vue'
import { assessmentsService } from '@/services/assessmentsService'
import { useAuthStore } from '@/stores/auth'
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
    expect(wrapper.find('.assessment-timeline__actor').text()).toBe(
      'منيرة بنت خالد العتيبي — System Administrator',
    )
    expect(wrapper.find('.assessment-record__title').text()).toContain('SLF-2026-000004')
    expect(wrapper.find('.assessment-record__badges .frozen-badge').exists()).toBe(true)
    expect(wrapper.find('.assessment-notice--frozen').exists()).toBe(true)
    expect(wrapper.find('.assessment-record__details').text()).toContain('ENT-007002')
    expect(wrapper.find('.result-pending').exists()).toBe(true)
    expect(wrapper.find('a[href="/admin/entities/7002"]').exists()).toBe(true)
    expect(wrapper.find('input').exists()).toBe(false)
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

describe('entity assessments card', () => {
  it('lists the entity assessments, frozen ones included, with links to the staff record', async () => {
    assessmentsService.list.mockResolvedValue({
      data: [FROZEN],
      meta: { pagination: { total: 1 } },
    })
    const wrapper = await mountAs(EntityAssessmentsCard, ['system_admin'], { entityId: 7002 })
    expect(assessmentsService.list).toHaveBeenCalledWith({
      'filter[entityId]': 7002,
      sort: '-created_at',
      perPage: 10,
    })
    expect(wrapper.find('a[href="/admin/assessments/9004"]').text()).toBe('SLF-2026-000004')
    expect(wrapper.find('.frozen-badge').exists()).toBe(true)
  })

  it('shows the empty and error states', async () => {
    assessmentsService.list.mockResolvedValueOnce({ data: [], meta: {} })
    const empty = await mountAs(EntityAssessmentsCard, ['system_admin'], { entityId: 7002 })
    expect(empty.find('.entity-assessments__empty').exists()).toBe(true)
    assessmentsService.list.mockRejectedValueOnce(new Error('down'))
    const failed = await mountAs(EntityAssessmentsCard, ['system_admin'], { entityId: 7002 })
    expect(failed.find('.error-state').exists()).toBe(true)
  })
})
