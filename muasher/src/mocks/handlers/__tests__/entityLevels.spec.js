import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...handlers)

const ADMIN = { id: 2, roles: ['system_admin'] }
const FOLLOWER = { id: 9, roles: ['request_follower'] }
const FINANCE = { id: 31, roles: ['finance_officer'] }
const ALBIRR = { id: 5001, roles: ['entity'], entityId: 5001 }
const LIST = '/entities?per_page=50'

async function call(path) {
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, {
    headers: { 'accept-language': 'en' },
  })
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null }
}

function levelsOf(rows) {
  return Object.fromEntries(rows.map((row) => [row.id, row.level]))
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetCriteriaStore()
  resetEntitiesMock()
  resetFinanceState()
  resetAssessmentsStore()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('entity level mock — staff list', () => {
  it('derives the level from the latest issued accredited certificate only', async () => {
    seedMockSession(ADMIN)
    const { body } = await call(LIST)
    const levels = levelsOf(body.data)
    expect(levels).toMatchObject({
      5001: 'silver',
      6002: 'gold',
      6003: null,
      6004: 'participation',
      6005: 'diamond',
      6006: null,
      7005: 'bronze',
    })
    const gold = body.data.find((row) => row.id === 6002)
    expect(gold.level_certificate_number).toBe('CRT-2026-000010')
    expect(gold.has_open_accredited_request).toBe(true)
    expect(body.data.find((row) => row.id === 6005).has_open_accredited_request).toBe(false)
  })

  it('filters by level and narrows the stats with the filter', async () => {
    seedMockSession(ADMIN)
    const gold = await call(`${LIST}&filter[level]=gold`)
    expect(gold.body.data.map((row) => row.id)).toEqual([6002])
    expect(gold.body.meta.stats.total).toBe(1)
    const none = await call(`${LIST}&filter[level]=none`)
    expect(none.body.data.every((row) => row.level === null)).toBe(true)
    expect(none.body.meta.stats.total).toBe(none.body.data.length)
  })

  it('sorts none < participation < bronze < silver < gold < diamond and back', async () => {
    seedMockSession(ADMIN)
    const descending = (await call(`${LIST}&sort=-level`)).body.data.map((row) => row.level)
    expect(descending.slice(0, 4)).toEqual(['diamond', 'gold', 'silver', 'silver'])
    expect(descending.at(-1)).toBeNull()
    const ascending = (await call(`${LIST}&sort=level`)).body.data.map((row) => row.level)
    expect(ascending[0]).toBeNull()
    expect(ascending.at(-1)).toBe('diamond')
  })

  it('rejects an unknown level and the in-progress bucket with 422', async () => {
    seedMockSession(ADMIN)
    for (const value of ['platinum', 'in_progress']) {
      const { status, body } = await call(`${LIST}&filter[level]=${value}`)
      expect(status).toBe(422)
      expect(body.errors).toHaveProperty(['filter.level'])
    }
  })

  it('gives the level but not the open-request flag to the Finance Officer', async () => {
    seedMockSession(FINANCE)
    const { body } = await call(LIST)
    const row = body.data.find((item) => item.id === 6002)
    expect(row.level).toBe('gold')
    expect(row).not.toHaveProperty('has_open_accredited_request')
  })
})

describe('entity level mock — queue, dashboards and the entity home', () => {
  it('adds the entity level to the request queue rows', async () => {
    seedMockSession(FOLLOWER)
    const { body } = await call('/requests?filter[path]=accredited&per_page=50')
    const ehsan = body.data.find((row) => row.entity?.id === 6002)
    expect(ehsan.entity.level).toBe('gold')
    expect(ehsan.entity).not.toHaveProperty('level_certificate_number')
  })

  it('returns entities_by_level with fixed keys and zero counts kept', async () => {
    seedMockSession(ADMIN)
    const { body } = await call('/dashboard/summary?board=system_admin')
    const widget = body.data.widgets.find((item) => item.key === 'entities_by_level')
    expect(widget.data.map((item) => item.key)).toEqual([
      'none',
      'in_progress',
      'participation',
      'bronze',
      'silver',
      'gold',
      'diamond',
    ])
    const counts = Object.fromEntries(widget.data.map((item) => [item.key, item.count]))
    expect(counts).toMatchObject({ participation: 1, bronze: 1, gold: 1, diamond: 1 })
    expect(counts.in_progress).toBeGreaterThan(0)
  })

  it('keeps the widget off the follower board', async () => {
    seedMockSession(FOLLOWER)
    const { body } = await call('/dashboard/summary')
    expect(body.data.widgets.some((item) => item.key === 'entities_by_level')).toBe(false)
  })

  it('adds the evidence count card, accredited mains and the action-center time', async () => {
    seedMockSession(ALBIRR)
    const board = (await call('/dashboard/summary?board=entity')).body.data
    const card = board.cards.find((item) => item.key === 'accepted_evidence_count')
    expect(card).toMatchObject({ format: 'count', link: null })
    expect(card.value).toBeGreaterThan(0)
    const mains = board.widgets.find((item) => item.key === 'criteria_progress').data
    expect(mains.length).toBeGreaterThan(0)
    expect(Object.keys(mains[0]).sort()).toEqual(['code', 'name', 'percent'])
    const center = await call('/dashboard/action-center')
    expect(center.body.meta.generated_at).toMatch(/^\d{4}-\d{2}-\d{2}T/)
  })
})

describe('insights mock — Task-69 fields', () => {
  it('adds the certificate number to every result reference', async () => {
    seedMockSession(ALBIRR)
    const { body } = await call('/entities/me/insights')
    expect(body.data.selected.certificate).toMatchObject({ number: 'CRT-2026-000001' })
  })

  it('offers the feedback report PDF as a signed link on the library row', async () => {
    seedMockSession(ALBIRR)
    const { body } = await call('/entities/me/report-library')
    const feedback = body.data[0].items.find((item) => item.kind === 'feedback_report')
    expect(feedback.pdf_available).toBe(true)
    expect(feedback.pdf_url).toMatch(/\/api\/v1\/files\/[0-9a-f-]{36}\/download\?.*signature=/)
    const board = body.data[0].items.find((item) => item.kind === 'board_report')
    expect(board).not.toHaveProperty('pdf_url')
  })

  it('returns a null logo link on the executive summary when the entity has no logo', async () => {
    seedMockSession(ALBIRR)
    const overview = (await call('/entities/me/insights')).body.data
    const { body } = await call(
      `/assessments/${overview.selected.assessment_id}/insights/executive-summary`,
    )
    expect(body.data.entity).toMatchObject({ has_logo: false, logo_url: null })
  })
})
