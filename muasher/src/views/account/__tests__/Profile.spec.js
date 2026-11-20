import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Profile from '@/views/account/Profile.vue'
import { profileService } from '@/services/profileService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/profileService', () => ({
  profileService: { getMyPermissions: vi.fn() },
}))

vi.mock('@/services/authService', () => ({
  authService: { changePassword: vi.fn() },
}))

vi.mock('@/services/identityService', () => ({
  identityService: { myChanges: vi.fn().mockResolvedValue({ data: [] }) },
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

describe('Account profile — my roles, without the permissions list', () => {
  it('shows the account identity and every granted role', async () => {
    const wrapper = await mountView()
    expect(wrapper.text()).toContain('نورة بنت سعد')
    expect(wrapper.text()).toContain('noura@example.sa')
    const roles = wrapper.findAll('.account-page__role-list .badge').map((badge) => badge.text())
    expect(roles).toEqual(['متابع طلبات التقييم', 'مسؤول المحتوى'])
  })

  it('does not list the granted permissions', async () => {
    const wrapper = await mountView('en')
    expect(wrapper.find('#account-permissions-title').exists()).toBe(false)
    expect(wrapper.find('.permission-module').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Manage KPIs')
  })

  it('keeps roles and identity read-only and offers the password change in place', async () => {
    const wrapper = await mountView()
    expect(wrapper.find('.account-page__grid').findAll('input, select, textarea')).toHaveLength(0)
    expect(wrapper.find('[data-testid="change-password-form"]').exists()).toBe(true)
  })

  it('offers the profile photo section to a person account', async () => {
    const wrapper = await mountView()
    expect(wrapper.find('[data-testid="profile-photo"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="profile-photo-entity-hint"]').exists()).toBe(false)
  })

  it('points an entity account to the entity logo instead of a personal photo', async () => {
    const globals = createTestGlobals({ locale: 'en' })
    useAuthStore(globals.pinia).user = { name: 'Entity', roles: ['entity'], entityId: 5001 }
    const wrapper = mount(Profile, { global: globals })
    await flushPromises()
    expect(wrapper.find('[data-testid="profile-photo"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="profile-photo-entity-hint"]').text()).toContain(
      'entity profile',
    )
  })

  it('offers retry when loading fails', async () => {
    profileService.getMyPermissions.mockRejectedValueOnce(new Error('offline'))
    const wrapper = await mountView('en')
    expect(wrapper.find('.error-state').exists()).toBe(true)
    await wrapper.find('.error-state__retry .btn').trigger('click')
    await flushPromises()
    expect(wrapper.findAll('.account-page__role-list .badge')).toHaveLength(2)
  })

  it('says so when no role is assigned', async () => {
    profileService.getMyPermissions.mockResolvedValue({ data: { roles: [], permissions: [] } })
    const wrapper = await mountView('en')
    expect(wrapper.text()).toContain('No roles are assigned')
  })
})
