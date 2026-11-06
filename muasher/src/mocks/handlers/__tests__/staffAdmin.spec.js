import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { contactInboxHandlers } from '@/mocks/handlers/contactInbox'
import { contactSettingsHandlers } from '@/mocks/handlers/contactSettings'
import { legalVersionsHandlers } from '@/mocks/handlers/legalVersions'
import { legalHandlers } from '@/mocks/handlers/legal'
import { auditLogsHandlers } from '@/mocks/handlers/auditLogs'
import { notificationTypesHandlers } from '@/mocks/handlers/notificationTypes'
import { certificatesStaffHandlers } from '@/mocks/handlers/certificatesStaff'
import { resetContactInbox } from '@/mocks/stores/contactInbox'
import { resetLegalVersions } from '@/mocks/stores/legalVersionsStore'
import { MOCK_API_BASE, clearMockSession, mockFetch, seedMockSession } from '@/test-utils/mockApi'
import { setRandomFaults } from '@/mocks/contract/faults'

const server = setupServer(
  ...contactInboxHandlers,
  ...contactSettingsHandlers,
  ...legalHandlers,
  ...legalVersionsHandlers,
  ...auditLogsHandlers,
  ...notificationTypesHandlers,
  ...certificatesStaffHandlers,
)
const ADMIN = { id: 1, roles: ['system_admin'] }
const CONTENT = { id: 31, roles: ['content_officer'] }
const ENTITY = { id: 7601, roles: ['entity'], entityId: 6001 }
const NEW_MESSAGE = '7d0c3c1e-4a51-4f0e-9a51-1a2b3c4d5e01'

async function call(method, path, body) {
  const init = { method }
  if (body !== undefined) {
    init.headers = { 'content-type': 'application/json' }
    init.body = JSON.stringify(body)
  }
  const response = await mockFetch(`${MOCK_API_BASE}${path}`, init)
  const text = await response.text()
  return {
    status: response.status,
    headers: response.headers,
    body: text ? safeJson(text) : null,
    text,
  }
}

function safeJson(text) {
  try {
    return JSON.parse(text)
  } catch {
    return null
  }
}

beforeAll(() => {
  setRandomFaults(false)
  server.listen({ onUnhandledRequest: 'error' })
})
beforeEach(() => {
  resetContactInbox()
  resetLegalVersions()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('contact inbox mock', () => {
  it('lists with stats, filters and assignees for a handler only', async () => {
    seedMockSession(ENTITY)
    expect((await call('GET', '/admin/contact-messages')).status).toBe(403)
    seedMockSession(ADMIN)
    const all = await call('GET', '/admin/contact-messages')
    expect(all.body.meta.stats).toEqual({ new: 1, inProgress: 1, replied: 1, closed: 1 })
    const linked = await call('GET', '/admin/contact-messages?filter[linked]=true')
    expect(linked.body.data.map((row) => row.reference)).toEqual(['CNT-2026-000052'])
    const assignees = await call('GET', '/admin/contact-messages/assignees')
    expect(assignees.body.data.length).toBeGreaterThan(0)
  })

  it('assigns, replies, closes and refuses illegal transitions', async () => {
    seedMockSession(ADMIN)
    const refused = await call('POST', `/admin/contact-messages/${NEW_MESSAGE}/assign`, {
      assigneeId: 999,
    })
    expect(refused.status).toBe(422)
    expect(refused.body.errors.assigneeId).toBeDefined()
    const assigned = await call('POST', `/admin/contact-messages/${NEW_MESSAGE}/assign`, {
      assigneeId: 31,
    })
    expect(assigned.body.data.status).toBe('in-progress')
    const replied = await call('POST', `/admin/contact-messages/${NEW_MESSAGE}/replies`, {
      body: 'رد الفريق',
    })
    expect(replied.status).toBe(201)
    expect(replied.body.data.status).toBe('replied')
    await call('POST', `/admin/contact-messages/${NEW_MESSAGE}/close`, {})
    const late = await call('POST', `/admin/contact-messages/${NEW_MESSAGE}/replies`, {
      body: 'رد متأخر',
    })
    expect(late.status).toBe(409)
    expect(late.body.code).toBe('contact.message_closed')
    const again = await call('POST', `/admin/contact-messages/${NEW_MESSAGE}/close`, {})
    expect(again.body.code).toBe('state.transition_illegal')
  })

  it('requires the Super Admin reason on writes', async () => {
    seedMockSession({ ...ADMIN, superAdmin: true })
    const refused = await call('POST', `/admin/contact-messages/${NEW_MESSAGE}/notes`, {
      body: 'ملاحظة',
    })
    expect(refused.body.code).toBe('reason_required')
  })
})

describe('contact settings and privacy mock', () => {
  it('keeps the retention inside its bounds', async () => {
    seedMockSession(ADMIN)
    expect((await call('GET', '/settings/contact-messages')).body.data.retentionMonths).toBe(24)
    expect((await call('PUT', '/settings/contact-messages', { retentionMonths: 200 })).status).toBe(
      422,
    )
    expect(
      (await call('PUT', '/settings/contact-messages', { retentionMonths: 12 })).body.data
        .retentionMonths,
    ).toBe(12)
  })

  it('exports without internal notes and counts the erased entries', async () => {
    seedMockSession(ADMIN)
    const exported = await call('POST', '/privacy/contact-messages/export', {
      email: 'finance@albayan.example.sa',
      reason: 'طلب صاحب البيانات رقم 12',
    })
    expect(exported.body.data.messages[0].entries.every((entry) => entry.kind !== 'note')).toBe(
      true,
    )
    const erased = await call('POST', '/privacy/contact-messages/erase', {
      email: 'finance@albayan.example.sa',
      reason: 'طلب صاحب البيانات رقم 12',
    })
    expect(erased.body.data).toEqual({ erasedMessages: 1, erasedEntries: 2 })
  })
})

describe('legal versions mock', () => {
  it('runs the draft → publish cycle and serves the new public text', async () => {
    seedMockSession(CONTENT)
    const list = await call('GET', '/admin/content/legal/privacy/versions')
    expect(list.body.data.map((row) => row.status)).toEqual(['current', 'superseded'])
    expect(list.body.data[0].body).toBeUndefined()
    const created = await call('POST', '/admin/content/legal/privacy/versions', {
      version: '2026-12',
      title: { ar: 'سياسة الخصوصية', en: 'Privacy policy' },
      body: { ar: '## عنوان\n\nنص', en: '## Title\n\nText' },
    })
    expect(created.status).toBe(201)
    const second = await call('POST', '/admin/content/legal/privacy/versions', {
      version: '2026-13',
      title: { ar: 'ع', en: 'T' },
      body: { ar: 'ن', en: 'T' },
    })
    expect(second.body.code).toBe('legal.draft_exists')
    const html = await call('PUT', `/admin/content/legal/versions/${created.body.data.id}`, {
      body: { ar: '<p>نص</p>', en: 'Text' },
    })
    expect(html.status).toBe(422)
    expect(html.body.errors['body.ar']).toBeDefined()
    const published = await call(
      'POST',
      `/admin/content/legal/versions/${created.body.data.id}/publish`,
      {},
    )
    expect(published.body.data.status).toBe('current')
    const edit = await call('PUT', `/admin/content/legal/versions/${created.body.data.id}`, {
      version: 'x',
    })
    expect(edit.body.code).toBe('legal.version_not_draft')
    const publicText = await call('GET', '/content/legal/privacy')
    expect(publicText.body.data.version).toBe('2026-12')
    expect(publicText.body.data.body.en[0]).toEqual({ type: 'heading', level: 2, text: 'Title' })
  })

  it('refuses accounts without the content permission', async () => {
    seedMockSession(ENTITY)
    expect((await call('GET', '/admin/content/legal/terms/versions')).status).toBe(403)
  })
})

describe('audit log mock', () => {
  it('filters, validates and exports as CSV with the row count', async () => {
    seedMockSession(ADMIN)
    const filtered = await call('GET', '/audit-logs?filter[event]=entity.updated')
    expect(filtered.body.data.map((row) => row.id)).toEqual([1844])
    expect((await call('GET', '/audit-logs?filter[from]=2026/11/01')).status).toBe(422)
    const exported = await call('POST', '/audit-logs/export', {
      filter: { event: 'entity.updated' },
    })
    expect(exported.status).toBe(200)
    expect(exported.headers.get('x-export-row-count')).toBe('1')
    expect(exported.text).toContain('entity.updated')
  })

  it('is readable by the System Administrator only', async () => {
    seedMockSession(CONTENT)
    expect((await call('GET', '/audit-logs')).status).toBe(403)
  })
})

describe('notification types and staff certificate mocks', () => {
  it('serves the read-only catalogue to the System Administrator', async () => {
    seedMockSession(ADMIN)
    const { body } = await call('GET', '/settings/notifications/types?filter[module]=CNT')
    expect(body.data.every((row) => row.module === 'CNT')).toBe(true)
    expect(body.meta.total).toBe(body.data.length)
    seedMockSession(ENTITY)
    expect((await call('GET', '/settings/notifications/types')).status).toBe(403)
  })

  it('lists the certificates of a request for a manager and re-issues them', async () => {
    seedMockSession(ADMIN)
    const { body } = await call('GET', '/certificates?filter[assessmentId]=9104')
    const current = body.data.find((row) => row.status === 'issued')
    expect(current.issuedBy).toBeDefined()
    const short = await call('POST', `/certificates/${current.id}/reissue`, { reason: 'قصير' })
    expect(short.status).toBe(422)
    const reissued = await call('POST', `/certificates/${current.id}/reissue`, {
      reason: 'إعادة إصدار بعد تحديث بيانات الجهة',
    })
    expect(reissued.status).toBe(201)
    expect(reissued.body.data.replaces.id).toBe(current.id)
  })
})
