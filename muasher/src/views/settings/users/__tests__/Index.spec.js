import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import UsersIndex from '@/views/settings/users/Index.vue'
import { usersService } from '@/services/usersService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/usersService', () => ({
  usersService: { list: vi.fn(), create: vi.fn(), update: vi.fn() },
}))

const USER = {
  id: 41,
  name: 'نورة العتيبي',
  email: 'noura@example.sa',
  roles: ['content_officer'],
  status: 'active',
  lastLoginAt: null,
}

async function mountPage(user, locale = 'en') {
  const globals = createTestGlobals({ locale })
  useAuthStore(globals.pinia).setUser(user)
  const wrapper = mount(UsersIndex, { global: globals, attachTo: document.body })
  await flushPromises()
  return wrapper
}

const headerActions = (wrapper) => wrapper.find('.users-page__header .users-page__actions')

beforeEach(() => {
  vi.clearAllMocks()
  usersService.list.mockResolvedValue({
    data: [USER],
    meta: { pagination: { page: 1, perPage: 10, total: 1, lastPage: 1 } },
  })
})

describe('users page actions', () => {
  it.each([
    ['en', ['Roles & permissions', 'Identity change requests', 'Invite user']],
    ['ar', ['الأدوار والصلاحيات', 'طلبات تعديل الهوية', 'دعوة مستخدم']],
  ])(
    'groups every page action in the header, invite last as the primary (%s)',
    async (locale, labels) => {
      const wrapper = await mountPage(sessionUserFor(['system_admin']), locale)
      const actions = headerActions(wrapper).element.children
      expect([...actions].map((action) => action.textContent.trim())).toEqual(labels)
      expect(actions[2].classList).toContain('btn--primary')
      expect(
        [...actions].slice(0, 2).every((link) => link.classList.contains('btn--secondary')),
      ).toBe(true)
      wrapper.unmount()
    },
  )

  it('keeps only search, filter and export in the table toolbar', async () => {
    const wrapper = await mountPage(sessionUserFor(['system_admin']))
    const toolbar = wrapper.find('.filter-toolbar')
    expect(toolbar.find('.toolbar__actions').exists()).toBe(false)
    expect(toolbar.text()).not.toContain('Invite user')
    expect(toolbar.text()).toContain('Export CSV')
    wrapper.unmount()
  })

  it('opens the invite form from the header button', async () => {
    const wrapper = await mountPage(sessionUserFor(['system_admin']))
    await headerActions(wrapper).find('.btn--primary').trigger('click')
    await flushPromises()
    expect(document.body.querySelector('input[name="email"]')).not.toBeNull()
    wrapper.unmount()
  })

  it('shows no header action to a user who may only view the list', async () => {
    const wrapper = await mountPage(sessionUserFor([], { permissions: ['users.view'] }))
    expect(headerActions(wrapper).element.children).toHaveLength(0)
    wrapper.unmount()
  })
})
