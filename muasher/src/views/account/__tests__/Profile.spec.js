import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Profile from '@/views/account/Profile.vue'
import { profileService } from '@/services/profileService'
import { useAuthStore } from '@/stores/auth'
import { groupPermissionsByModule } from '@/composables/useMyPermissions'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/profileService', () => ({
  profileService: { getMyPermissions: vi.fn() },
}))

const payload = {
  roles: [
    {
      key: 'request_follower',
      label: { ar: 'متابع طلبات التقييم', en: 'Assessment Requests Follower' },
    },
    { key: 'content_officer', label: { ar: 'مسؤول المحتوى', en: 'Content Officer' } },
  ],
  permissions: [
    { key: 'admin.kpis', module: 'content', label: { ar: 'إدارة المؤشرات', en: 'Manage KPIs' } },
    {
      key: 'dashboard.view',
      module: 'dashboard',
      label: { ar: 'عرض لوحة التحكم', en: 'View dashboard' },
    },
    {
      key: 'requests.queue',
      module: 'requests',
      label: { ar: 'قائمة الطلبات', en: 'Requests queue' },
    },
  ],
}

async function mountView(locale = 'ar') {
  const globals = createTestGlobals({ locale })
  useAuthStore(globals.pinia).user = {
    name: { ar: 'نورة بنت سعد', en: 'Noura Saad' },
    email: 'noura@example.sa',
    roles: ['request_follower', 'content_officer'],
  }
  const wrapper = mount(Profile, { global: globals })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  profileService.getMyPermissions.mockResolvedValue({ data: payload })
})

describe('Account profile — my roles and permissions (Q-42)', () => {
  it('shows the account identity and every granted role', async () => {
    const wrapper = await mountView()
    expect(wrapper.text()).toContain('نورة بنت سعد')
    expect(wrapper.text()).toContain('noura@example.sa')
    const roles = wrapper.findAll('.account-page__role-list .badge').map((badge) => badge.text())
    expect(roles).toEqual(['متابع طلبات التقييم', 'مسؤول المحتوى'])
  })

  it('groups permissions by module in catalog order with bilingual labels', async () => {
    const wrapper = await mountView('en')
    const titles = wrapper.findAll('.permission-module__title').map((el) => el.text())
    expect(titles).toEqual(['Dashboard', 'Requests', 'Content'])
    expect(wrapper.text()).toContain('Manage KPIs')
    expect(wrapper.find('.permission-module__count').text()).toBe('1 permission')
  })

  it('is read-only: no form controls to change roles or permissions', async () => {
    const wrapper = await mountView()
    expect(wrapper.findAll('input, select, textarea')).toHaveLength(0)
  })

  it('offers retry when loading fails', async () => {
    profileService.getMyPermissions.mockRejectedValueOnce(new Error('offline'))
    const wrapper = await mountView('en')
    expect(wrapper.find('.error-state').exists()).toBe(true)
    await wrapper.find('.error-state__retry .btn').trigger('click')
    await flushPromises()
    expect(wrapper.findAll('.permission-module')).toHaveLength(3)
  })

  it('shows the empty state when no permission is granted', async () => {
    profileService.getMyPermissions.mockResolvedValue({ data: { roles: [], permissions: [] } })
    const wrapper = await mountView('en')
    expect(wrapper.find('.empty-state').text()).toContain('No permissions')
    expect(wrapper.text()).toContain('No roles are assigned')
  })
})

describe('groupPermissionsByModule', () => {
  it('puts unknown modules last', () => {
    const groups = groupPermissionsByModule([
      { key: 'x', module: 'future', label: 'x' },
      { key: 'y', module: 'dashboard', label: 'y' },
    ])
    expect(groups.map((group) => group.module)).toEqual(['dashboard', 'future'])
  })
})
