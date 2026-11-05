import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import { notificationTypesService } from '@/services/notificationTypesService'
import { certificatesService } from '@/services/certificatesService'
import { contactInboxService } from '@/services/contactInboxService'
import { contactSettingsService } from '@/services/contactSettingsService'
import { contactPrivacyService } from '@/services/contactPrivacyService'
import { legalVersionsService } from '@/services/legalVersionsService'
import { auditLogService } from '@/services/auditLogService'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

function respond(method, data, meta = {}) {
  apiClient[method].mockResolvedValue({ data: { data, meta, errors: null } })
}

beforeEach(() => vi.clearAllMocks())

describe('staff services on the real contracts', () => {
  it('reads the notification catalogue with filters', async () => {
    respond('get', [{ code: 'CNT-01', channels: ['in_app'], isMandatory: true }])
    const { data } = await notificationTypesService.list({ module: 'CNT', category: '' })
    expect(apiClient.get).toHaveBeenCalledWith('/settings/notifications/types', {
      params: { 'filter[module]': 'CNT' },
    })
    expect(data[0]).toMatchObject({ code: 'CNT-01', audiences: [], userConfigurableChannels: [] })
  })

  it('lists the certificates of a request and calls the staff actions', async () => {
    respond('get', [
      { id: 'c1', number: 'CRT-1', status: 'issued', issuedBy: { id: 10, name: 'A' } },
    ])
    const { data } = await certificatesService.listForRequest(14)
    expect(apiClient.get).toHaveBeenCalledWith('/certificates', {
      params: { 'filter[assessmentId]': 14, perPage: 20 },
    })
    expect(data[0]).toMatchObject({
      id: 'c1',
      issuedBy: { id: 10, name: 'A' },
      reissueReason: null,
    })
    respond('post', { id: 'c2', status: 'issued' })
    await certificatesService.revoke('c1', 'سبب الإلغاء المكتوب بوضوح')
    await certificatesService.reissue('c1', 'سبب إعادة الإصدار المكتوب')
    await certificatesService.issueForRequest(14, { reason: 'سبب الإصدار' })
    expect(apiClient.post.mock.calls.map(([path, body]) => [path, body])).toEqual([
      ['/certificates/c1/revoke', { reason: 'سبب الإلغاء المكتوب بوضوح' }],
      ['/certificates/c1/reissue', { reason: 'سبب إعادة الإصدار المكتوب' }],
      ['/assessments/14/certificate', { reason: 'سبب الإصدار' }],
    ])
  })

  it('keeps 409 / 422 certificate refusals out of GlitchTip', async () => {
    const refusal = Object.assign(new Error('409'), { response: { status: 409 } })
    apiClient.post.mockRejectedValue(refusal)
    await expect(certificatesService.revoke('c1', 'reason text long')).rejects.toBe(refusal)
    expect(logger.error).not.toHaveBeenCalled()
  })

  it('drives the contact inbox endpoints', async () => {
    respond('get', [{ id: 'm1', reference: 'CNT-1', status: 'new', sender: { name: 'S' } }], {
      stats: { new: 1, inProgress: 2 },
    })
    const list = await contactInboxService.list({ 'filter[status]': 'new' })
    expect(apiClient.get).toHaveBeenCalledWith('/admin/contact-messages', {
      params: { 'filter[status]': 'new' },
    })
    expect(list.meta.stats).toEqual({ new: 1, inProgress: 2, replied: 0, closed: 0 })
    expect(list.data[0].sender).toMatchObject({ name: 'S', email: '' })
    respond('post', { id: 'm1', status: 'in-progress' })
    await contactInboxService.assign('m1', { assigneeId: 31 })
    await contactInboxService.addNote('m1', { body: 'ملاحظة' })
    await contactInboxService.reply('m1', { body: 'رد' })
    await contactInboxService.close('m1')
    await contactInboxService.reopen('m1', { reason: 'سبب إعادة الفتح' })
    expect(apiClient.post.mock.calls.map(([path]) => path)).toEqual([
      '/admin/contact-messages/m1/assign',
      '/admin/contact-messages/m1/notes',
      '/admin/contact-messages/m1/replies',
      '/admin/contact-messages/m1/close',
      '/admin/contact-messages/m1/reopen',
    ])
  })

  it('reads and writes the contact retention and the privacy tools', async () => {
    respond('get', { retentionMonths: 24 })
    const { data } = await contactSettingsService.get()
    expect(data).toEqual({ retentionMonths: 24, retentionLimits: { min: 1, max: 120 } })
    respond('put', { retentionMonths: 12, retentionLimits: { min: 1, max: 120 } })
    await contactSettingsService.update({ retentionMonths: 12 })
    expect(apiClient.put).toHaveBeenCalledWith('/settings/contact-messages', {
      retentionMonths: 12,
    })
    respond('post', { messages: [] })
    await contactPrivacyService.exportByEmail({
      email: 'a@example.sa',
      reason: 'طلب صاحب البيانات',
    })
    await contactPrivacyService.eraseByEmail({ email: 'a@example.sa', reason: 'طلب صاحب البيانات' })
    expect(apiClient.post.mock.calls.map(([path]) => path)).toEqual([
      '/privacy/contact-messages/export',
      '/privacy/contact-messages/erase',
    ])
  })

  it('manages the legal versions', async () => {
    respond('get', [{ id: 15, status: 'draft', title: { ar: 'ع', en: 'T' } }])
    const { data } = await legalVersionsService.list('terms')
    expect(apiClient.get).toHaveBeenCalledWith('/admin/content/legal/terms/versions', {
      params: { perPage: 50 },
    })
    expect(data[0]).toMatchObject({ id: 15, status: 'draft', body: null })
    respond('post', { id: 16, status: 'draft', body: { ar: 'نص', en: 'Text' } })
    respond('put', { id: 16 })
    apiClient.delete.mockResolvedValue({ data: null })
    await legalVersionsService.createDraft('privacy', { version: '2026-12' })
    await legalVersionsService.updateDraft(16, { version: '2026-12a' })
    await legalVersionsService.publish(16, { reason: 'سبب النشر' })
    await legalVersionsService.discardDraft(16)
    expect(apiClient.post.mock.calls.map(([path]) => path)).toEqual([
      '/admin/content/legal/privacy/versions',
      '/admin/content/legal/versions/16/publish',
    ])
    expect(apiClient.put).toHaveBeenCalledWith('/admin/content/legal/versions/16', {
      version: '2026-12a',
    })
    expect(apiClient.delete).toHaveBeenCalledWith('/admin/content/legal/versions/16')
  })

  it('lists the audit log and downloads the CSV export', async () => {
    respond('get', [{ id: 1, event: 'entity.updated', actor: { kind: 'user', id: 1 } }])
    const { data } = await auditLogService.list({ 'filter[event]': 'entity.updated' })
    expect(apiClient.get).toHaveBeenCalledWith('/audit-logs', {
      params: { 'filter[event]': 'entity.updated' },
    })
    expect(data[0].actor).toEqual({ kind: 'user', id: 1, name: null, roles: [] })
    const blob = new Blob(['id'])
    apiClient.post.mockResolvedValue({
      data: blob,
      headers: {
        'content-disposition': 'attachment; filename="audit-log-20261105-060000.csv"',
        'x-export-row-count': '7',
      },
    })
    const download = await auditLogService.exportCsv({ filter: {}, reason: 'مراجعة' })
    expect(apiClient.post).toHaveBeenCalledWith(
      '/audit-logs/export',
      { filter: {}, reason: 'مراجعة' },
      { responseType: 'blob' },
    )
    expect(download).toEqual({ blob, fileName: 'audit-log-20261105-060000.csv', rowCount: 7 })
  })
})
