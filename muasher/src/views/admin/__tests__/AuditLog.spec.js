import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AuditLog from '@/views/admin/AuditLog.vue'
import { auditLogService } from '@/services/auditLogService'
import { downloadBlob } from '@/utils/download'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/auditLogService', () => ({
  auditLogService: { list: vi.fn(), get: vi.fn(), exportCsv: vi.fn() },
}))
vi.mock('@/utils/download', () => ({ downloadBlob: vi.fn(), downloadJson: vi.fn() }))

const ENTRY = {
  id: 1844,
  event: 'entity.updated',
  actor: { kind: 'user', id: 1, name: 'ريم بنت خالد الحربي', roles: ['system_admin'] },
  subject: { type: 'entity', id: 6002 },
  oldValues: { status: 'active' },
  newValues: { status: 'deactivated' },
  reason: 'طلب مجلس إدارة الجهة إيقاف الحساب مؤقتًا.',
  tags: [],
  ipAddress: '10.20.30.40',
  userAgent: 'Mozilla/5.0',
  url: 'https://moashir.example.sa/api/v1/entities/6002/deactivate',
  requestId: 'req-1',
  createdAt: '2026-11-04T09:45:00+00:00',
}
const MASKED = {
  ...ENTRY,
  id: 1843,
  actor: { kind: 'super_admin', id: null, name: null, roles: ['super_admin'] },
}

async function mountView(roles = ['system_admin'], locale = 'ar') {
  const globals = createTestGlobals({ locale })
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles))
  const wrapper = mount(AuditLog, { global: globals, attachTo: document.body })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  auditLogService.list.mockResolvedValue({
    data: [ENTRY, MASKED],
    meta: { pagination: { page: 1, lastPage: 1, total: 2 } },
  })
})

describe('audit log screen', () => {
  it('lists the entries with the masked Super Admin actor and opens the details', async () => {
    const wrapper = await mountView()
    const rows = wrapper.findAll('tbody tr')
    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('entity.updated')
    expect(rows[0].text()).toContain('الجهات')
    expect(rows[1].text()).toContain('مدير النظام الرئيسي')
    await rows[0].find('button').trigger('click')
    await flushPromises()
    expect(document.body.textContent).toContain('"deactivated"')
    wrapper.unmount()
  })

  it('applies the filters only on submit and shows the server validation', async () => {
    const wrapper = await mountView()
    await wrapper.find('input[name="audit-event"]').setValue('entity.updated')
    expect(auditLogService.list).toHaveBeenCalledTimes(1)
    auditLogService.list.mockRejectedValueOnce({
      normalized: {
        status: 422,
        message: 'invalid',
        fieldErrors: { 'filter.event': ['Bad event'] },
      },
    })
    await wrapper.find('form.audit-log__filters').trigger('submit')
    await flushPromises()
    expect(auditLogService.list).toHaveBeenLastCalledWith(
      expect.objectContaining({ 'filter[event]': 'entity.updated', page: 1 }),
    )
    expect(wrapper.text()).toContain('Bad event')
    wrapper.unmount()
  })

  it('exports the filtered entries with an optional reason', async () => {
    const blob = new Blob(['id'])
    auditLogService.exportCsv.mockResolvedValue({ blob, fileName: 'audit.csv', rowCount: 2 })
    const wrapper = await mountView(['system_admin'], 'en')
    await wrapper
      .findAll('button')
      .find((item) => item.text() === 'Export CSV')
      .trigger('click')
    await flushPromises()
    const dialog = wrapper.findAllComponents({ name: 'ReasonDialog' })[0]
    expect(dialog.props('optional')).toBe(true)
    await dialog.props('action')('Quarterly review of assignments')
    await flushPromises()
    expect(auditLogService.exportCsv).toHaveBeenCalledWith({
      filter: {},
      reason: 'Quarterly review of assignments',
    })
    expect(downloadBlob).toHaveBeenCalledWith(blob, 'audit.csv')
    wrapper.unmount()
  })

  it('shows the forbidden state and no export without the permission', async () => {
    auditLogService.list.mockRejectedValue({ normalized: { status: 403, message: 'No' } })
    const wrapper = await mountView(['content_officer'], 'en')
    expect(wrapper.find('table').exists()).toBe(false)
    expect(wrapper.find('.empty-state').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Export CSV')
    wrapper.unmount()
  })
})
