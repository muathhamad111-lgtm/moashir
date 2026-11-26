import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import AuditLogTable from '@/components/audit/AuditLogTable.vue'
import { useAuthStore } from '@/stores/auth'
import { useTableColumnsStore } from '@/stores/tableColumns'
import { sessionUserFor } from '@/test-utils/session'
import { createTestGlobals } from '@/test-utils'

const ENTRY = {
  id: 1,
  event: 'auth.login',
  actor: { kind: 'user', id: 4, name: 'سعود الدوسري', roles: ['system_admin'] },
  subject: null,
  reason: null,
  tags: ['export'],
  ipAddress: '10.0.0.1',
  requestId: '0d6f0f8e-3c1a-4e8a-9b8e-2b6f4c6d0001',
  createdAt: '2026-11-10T09:00:00Z',
}

describe('AuditLogTable', () => {
  it('shows the actor roles and the request ID once the viewer chooses them', async () => {
    const globals = createTestGlobals({ locale: 'en' })
    setActivePinia(globals.pinia)
    useAuthStore().setUser(sessionUserFor(['system_admin']))
    const wrapper = mount(AuditLogTable, {
      props: { items: [ENTRY], status: 'success' },
      global: globals,
    })
    await flushPromises()
    useTableColumnsStore().choices = { 'audit-log': ['actorRoles', 'requestId'] }
    await flushPromises()
    const cells = wrapper.findAll('tbody td')
    expect(cells[1].text()).toBe('System Administrator')
    expect(cells[2].text()).toBe(ENTRY.requestId)
  })
})
