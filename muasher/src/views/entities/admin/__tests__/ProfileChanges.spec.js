import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import ProfileChanges from '@/views/entities/admin/ProfileChanges.vue'
import { profileChangeRequestsService } from '@/services/profileChangeRequestsService'
import { useTableColumnsStore } from '@/stores/tableColumns'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/profileChangeRequestsService', () => ({
  profileChangeRequestsService: { list: vi.fn(), show: vi.fn(), decide: vi.fn() },
}))

const ROW = {
  id: '6f1c2c1e-0a4b-4c0e-9d6a-1b2c3d4e5f60',
  status: 'pending',
  revision: 1,
  entity: { id: 7, reference: 'ENT-000007', name: { ar: 'جمعية الأمل', en: 'Al-Amal' } },
  items: ['employees_count', 'logo'],
  submittedAt: '2026-11-22T09:00:00Z',
}

async function mountQueue(locale = 'ar') {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  await globals.router.push('/admin/entities/profile-changes')
  const wrapper = mount(ProfileChanges, {
    global: { ...globals, stubs: { teleport: true } },
    attachTo: document.body,
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  profileChangeRequestsService.list.mockResolvedValue({
    data: [ROW],
    meta: { pagination: { page: 1, perPage: 10, total: 1, lastPage: 1 } },
  })
  profileChangeRequestsService.show.mockResolvedValue({ data: { ...ROW, changes: [] } })
})

describe('ProfileChanges', () => {
  it('lists the pending requests first, with the changed items and the count, in Arabic', async () => {
    const wrapper = await mountQueue('ar')
    expect(profileChangeRequestsService.list).toHaveBeenCalledWith(
      expect.objectContaining({ 'filter[status]': 'pending' }),
    )
    expect(wrapper.find('h1').text()).toBe('طلبات تعديل بيانات الجهات')
    expect(wrapper.find('[data-testid="profile-changes-count"]').text()).toBe('1 بانتظار القرار')
    expect(wrapper.text()).toContain('جمعية الأمل')
    expect(wrapper.text()).toContain('عدد الموظفين، شعار الجهة')
    wrapper.unmount()
  })

  it('names the queue in English', async () => {
    const wrapper = await mountQueue('en')
    expect(wrapper.find('h1').text()).toBe('Entity profile change requests')
    expect(wrapper.text()).toContain('Employees, Entity logo')
    wrapper.unmount()
  })

  it('opens the request for review', async () => {
    const wrapper = await mountQueue('ar')
    const review = wrapper.findAll('button').find((button) => button.text() === 'مراجعة')
    await review.trigger('click')
    await flushPromises()
    expect(profileChangeRequestsService.show).toHaveBeenCalledWith(ROW.id)
    expect(wrapper.find('[data-testid="profile-change-review"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('shows the reviewer, the revision and the decision date once chosen', async () => {
    profileChangeRequestsService.list.mockResolvedValue({
      data: [
        {
          ...ROW,
          status: 'approved',
          revision: 2,
          reviewedBy: 'سعود الدواسر',
          decidedAt: '2026-11-23T10:00:00Z',
        },
      ],
      meta: { pagination: { page: 1, perPage: 10, total: 1, lastPage: 1 } },
    })
    const wrapper = await mountQueue('en')
    useTableColumnsStore().choices = {
      'entities-profile-changes': ['items', 'revision', 'reviewedBy', 'decidedAt'],
    }
    await flushPromises()
    const cells = wrapper.findAll('tbody td').map((cell) => cell.text())
    expect(cells.slice(2, 5)).toEqual(['2', 'سعود الدواسر', expect.stringContaining('2026-11-23')])
    wrapper.unmount()
  })
})
