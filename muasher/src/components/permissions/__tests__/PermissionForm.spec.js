import { afterEach, describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import PermissionForm from '@/components/permissions/PermissionForm.vue'
import { createTestGlobals } from '@/test-utils'

const EDITABLE = ['system_admin', 'request_follower', 'assessor', 'finance_officer']

let wrapper

function permission(overrides = {}) {
  return {
    key: 'finance.operations.export',
    module: 'finance',
    label: { ar: 'تصدير العمليات المالية', en: 'Export financial operations' },
    description: { ar: 'تصدير مدقق.', en: 'Audited export.' },
    lockedToRoles: [],
    roles: ['finance_officer'],
    ...overrides,
  }
}

function factory(props = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  wrapper = mount(PermissionForm, {
    props: { modelValue: true, permission: permission(), editableRoles: EDITABLE, ...props },
    attachTo: document.body,
    global: { ...globals, stubs: { teleport: true } },
  })
  return wrapper
}

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
})

describe('PermissionForm — labels and role matrix only', () => {
  it('shows the key and module read-only, with no key, module or action inputs', () => {
    factory()
    expect(document.querySelector('.modal__title').textContent).toContain('Edit permission')
    expect(document.querySelector('.permission-form__facts').textContent).toContain('finance.operations.export')
    expect(document.querySelector('input[name="key"]')).toBeNull()
    expect(document.querySelector('select[name="group"]')).toBeNull()
    expect(document.querySelectorAll('input[name^="action-"]')).toHaveLength(0)
    expect(document.querySelectorAll('input[name^="role-"]')).toHaveLength(EDITABLE.length)
  })

  it('pre-checks the roles that hold the permission and emits labels + roles', async () => {
    factory()
    expect(document.querySelector('input[name="role-finance_officer"]').checked).toBe(true)
    await wrapper.find('input[name="role-system_admin"]').setValue(true)
    await wrapper.vm.form.onSubmit()
    await flushPromises()
    const [payload] = wrapper.emitted('saved').at(-1)
    expect(payload.label).toEqual({ ar: 'تصدير العمليات المالية', en: 'Export financial operations' })
    expect(payload.roles.sort()).toEqual(['finance_officer', 'system_admin'])
  })

  it('requires both labels', async () => {
    factory({ permission: permission({ label: { ar: '', en: '' } }) })
    await wrapper.vm.form.onSubmit()
    await flushPromises()
    expect(wrapper.emitted('saved')).toBeUndefined()
    expect(document.querySelectorAll('.form-field__error, [role="alert"]').length).toBeGreaterThan(0)
  })

  it('keeps a hard-locked permission read-only for every role', () => {
    factory({ permission: permission({ key: 'settings.branding.manage', lockedToRoles: ['system_admin'], roles: ['system_admin'] }) })
    expect(document.querySelector('.permission-form__locked')).toBeTruthy()
    document.querySelectorAll('input[name^="role-"]').forEach((input) => expect(input.disabled).toBe(true))
  })
})
