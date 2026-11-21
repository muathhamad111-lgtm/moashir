import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Permissions from '@/views/settings/Permissions.vue'
import { permissionsService } from '@/services/permissionsService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/permissionsService', () => ({
  permissionsService: {
    getMatrix: vi.fn(),
    updateLabels: vi.fn(() => Promise.resolve({ data: {} })),
    updateRolePermissions: vi.fn(() => Promise.resolve({ data: {} })),
  },
}))

const toastSuccess = vi.fn()
const toastError = vi.fn()
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: toastSuccess, error: toastError }),
}))

const PERMISSIONS_FIXTURE = [
  {
    key: 'users.view',
    module: 'admin',
    label: { ar: 'عرض المستخدمين', en: 'View users' },
    description: { ar: 'وصف', en: 'Read users.' },
    lockedToRoles: [],
    confidential: true,
  },
  {
    key: 'settings.branding.manage',
    module: 'settings',
    label: { ar: 'إدارة الهوية', en: 'Manage branding' },
    description: { ar: 'وصف', en: 'Branding.' },
    lockedToRoles: ['system_admin'],
    confidential: false,
  },
]

const ROLES_FIXTURE = [
  { key: 'system_admin', label: { ar: 'مدير النظام', en: 'System Administrator' } },
  { key: 'request_follower', label: { ar: 'متابع', en: 'Follower' } },
  { key: 'entity', label: { ar: 'الجهة', en: 'Entity' } },
]

function respondWithMatrix() {
  permissionsService.getMatrix.mockResolvedValue({
    data: {
      permissions: PERMISSIONS_FIXTURE,
      roles: ROLES_FIXTURE,
      matrix: {
        system_admin: ['users.view', 'settings.branding.manage'],
        request_follower: [],
        entity: [],
      },
    },
  })
}

async function mountPage(roles = ['system_admin']) {
  const globals = createTestGlobals({ locale: 'en' })
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles))
  const wrapper = mount(Permissions, {
    attachTo: document.body,
    global: { ...globals, stubs: { teleport: true } },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  document.body.innerHTML = ''
  respondWithMatrix()
})

describe('Permissions screen — matrix and labels only (C-05)', () => {
  it('lists the code-defined catalog with the roles that hold each key', async () => {
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('users.view')
    expect(wrapper.text()).toContain('System Administrator')
    expect(wrapper.text()).toContain('Locked')
  })

  it('offers no way to create or delete a permission', async () => {
    const wrapper = await mountPage()
    expect(wrapper.text()).not.toContain('Add permission')
    expect(permissionsService.create).toBeUndefined()
    expect(permissionsService.remove).toBeUndefined()
  })

  it('hides the edit action from a user without roles.matrix.manage', async () => {
    const wrapper = await mountPage([])
    expect(wrapper.find('.dropdown-menu').exists()).toBe(false)
  })

  it('saves labels and replaces only the changed role rows of the matrix', async () => {
    const wrapper = await mountPage()
    wrapper.vm.openEdit(wrapper.vm.filteredRows[0])
    await wrapper.vm.onSaved({
      label: { ar: 'عرض المستخدمين', en: 'View staff users' },
      description: { ar: 'وصف', en: 'Read users.' },
      roles: ['system_admin', 'request_follower'],
    })
    await flushPromises()
    expect(permissionsService.updateLabels).toHaveBeenCalledWith('users.view', {
      label: { ar: 'عرض المستخدمين', en: 'View staff users' },
      description: { ar: 'وصف', en: 'Read users.' },
    })
    expect(permissionsService.updateRolePermissions).toHaveBeenCalledTimes(1)
    expect(permissionsService.updateRolePermissions).toHaveBeenCalledWith('request_follower', [
      'users.view',
    ])
    expect(toastSuccess).toHaveBeenCalled()
  })

  it('reports a failed save without closing silently', async () => {
    permissionsService.updateLabels.mockRejectedValueOnce(new Error('offline'))
    const wrapper = await mountPage()
    wrapper.vm.openEdit(wrapper.vm.filteredRows[0])
    await wrapper.vm.onSaved({
      label: { ar: 'س', en: 'Changed' },
      description: { ar: 'وصف', en: 'Read users.' },
      roles: ['system_admin'],
    })
    await flushPromises()
    expect(toastError).toHaveBeenCalled()
  })

  it('shows the error state when the matrix cannot load', async () => {
    permissionsService.getMatrix.mockRejectedValue(new Error('offline'))
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain("We couldn't load the permissions")
  })
})
