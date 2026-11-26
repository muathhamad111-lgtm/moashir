import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetLegalVersions } from '@/mocks/stores/legalVersionsStore'
import { resetNdaStore } from '@/mocks/stores/ndaStore'
import { setMockScenario } from '@/mocks/stores/committee/committeeScenario'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...handlers)
const ADMIN = { id: 2, roles: ['system_admin'] }
const ASSESSOR = { id: 21, roles: ['assessor'] }
const FINANCE = { id: 22, roles: ['finance_officer'] }

async function call(method, path, body) {
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, {
    method,
    headers: { 'accept-language': 'en', 'content-type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  })
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null }
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetLegalVersions()
  resetNdaStore()
  setMockScenario(null)
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
  setMockScenario(null)
})
afterAll(() => server.close())

describe('agreement mock', () => {
  it('reports the seed version as accepted by default and owed in the nda-pending scenario', async () => {
    seedMockSession(ASSESSOR)
    expect((await call('GET', '/auth/session')).body.data.nda_acceptance_required).toBe(false)

    setMockScenario('nda-pending')
    const session = await call('GET', '/auth/session')
    const mine = await call('GET', '/me/nda')
    expect(session.body.data.nda_acceptance_required).toBe(true)
    expect(mine.body.data.version.version).toBe('1.0')
    expect(mine.body.data.version.body.en[0].type).toBe('heading')

    expect((await call('POST', '/me/nda/acceptances', { termsVersionId: 501 })).status).toBe(200)
    expect((await call('GET', '/auth/session')).body.data.nda_acceptance_required).toBe(false)
  })

  it('never asks staff outside the scope', async () => {
    setMockScenario('nda-pending')
    seedMockSession(FINANCE)

    expect((await call('GET', '/auth/session')).body.data.nda_acceptance_required).toBe(false)
  })

  it('lets an administrator draft and publish a version, which every in-scope account then owes', async () => {
    seedMockSession(ADMIN)
    const body = { ar: '## الغرض\n\nنص', en: '## Purpose\n\nText' }
    const title = { ar: 'اتفاقية', en: 'Agreement' }

    expect(
      (await call('POST', '/settings/nda/draft', { version: '1.1', title, body })).status,
    ).toBe(201)
    expect((await call('GET', '/settings/nda/draft/impact')).body.data.total).toBeGreaterThan(0)
    const refused = await call('POST', '/settings/nda/draft/publish', { reason: 'short' })
    expect(refused.status).toBe(422)
    const published = await call('POST', '/settings/nda/draft/publish', {
      changeSummary: { ar: 'تغيير', en: 'Change' },
      reason: 'Legal review of the clauses.',
      confirmIrreversible: true,
    })
    expect(published.body.data.status).toBe('current')
    expect((await call('GET', '/auth/session')).body.data.nda_acceptance_required).toBe(true)
  })

  it('reports the agreement status in the users list and filters the pending ones', async () => {
    setMockScenario('nda-pending')
    seedMockSession(ADMIN)

    const all = await call('GET', '/users?per_page=50')
    const pending = await call('GET', '/users?per_page=50&filter[nda]=pending')
    expect(all.body.meta.nda_scope_roles).toContain('assessor')
    expect(all.body.data.some((user) => user.nda_status === 'out_of_scope')).toBe(true)
    expect(pending.body.data.every((user) => user.nda_status === 'pending')).toBe(true)
  })

  it('limits manual reminders to one every 24 hours', async () => {
    setMockScenario('nda-pending')
    seedMockSession(ADMIN)

    expect((await call('POST', '/settings/nda/reminders')).status).toBe(202)
    expect((await call('POST', '/settings/nda/reminders')).status).toBe(429)
  })

  it('refuses the agreement settings to staff without nda.manage', async () => {
    seedMockSession(ASSESSOR)

    expect((await call('GET', '/settings/nda')).status).toBe(403)
  })
})
