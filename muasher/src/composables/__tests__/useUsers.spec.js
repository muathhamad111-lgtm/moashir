import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { useUsers, ROLE_ORDER } from '@/composables/useUsers'
import { createTestGlobals } from '@/test-utils'

function withComposable(locale = 'ar') {
  let api
  const Host = defineComponent({
    setup() {
      api = useUsers()
      return () => null
    },
  })
  mount(Host, { global: createTestGlobals({ locale }) })
  return api
}

describe('useUsers', () => {
  it('exposes the 8 staff role keys in a stable order, never entity, super_admin or a committee assessor role', () => {
    expect(ROLE_ORDER).toHaveLength(8)
    expect(ROLE_ORDER).not.toContain('entity')
    expect(ROLE_ORDER[0]).toBe('system_admin')
    expect(ROLE_ORDER).toContain('finance_officer')
    expect(ROLE_ORDER).not.toContain('super_admin')
    expect(ROLE_ORDER).not.toContain('committee-assessor')
    expect(ROLE_ORDER.some((role) => role.includes('integrations'))).toBe(false)
  })

  it('builds one option per role plus placeholders on the filter lists', () => {
    const { roleOptions, roleFilterOptions, statusFilterOptions } = withComposable()
    expect(roleOptions.value).toHaveLength(8)
    expect(roleFilterOptions.value).toHaveLength(9)
    expect(roleFilterOptions.value[0].value).toBe('')
    expect(statusFilterOptions.value.map((o) => o.value)).toEqual([
      '',
      'invited',
      'active',
      'locked',
      'suspended',
      'expired',
    ])
  })

  it('localizes role labels to the active locale', () => {
    const ar = withComposable('ar')
    expect(ar.roleLabel('system_admin')).toBe('مدير النظام')
    const en = withComposable('en')
    expect(en.roleLabel('system_admin')).toBe('System Administrator')
  })

  it('returns read-only capability hints derived per role', () => {
    const { roleCapabilities } = withComposable('en')
    expect(roleCapabilities('system_admin')).toEqual([
      'Administer users, settings, content and integrations within the granted permissions',
    ])
    expect(roleCapabilities('assessor').length).toBe(2)
    expect(roleCapabilities('')).toEqual([])
  })
})
