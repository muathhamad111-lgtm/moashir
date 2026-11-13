import { describe, expect, it, beforeAll, afterAll, afterEach } from 'vitest'
import { setupServer } from 'msw/node'
import { bagHandlers, resetBagStore } from '@/mocks/handlers/bag'
import { BAG_SOURCE_ASSESSMENT_ID, STAGE_1_OPEN_GAP_IDS } from '@/mocks/fixtures/bag'
import { MOCK_API_BASE, mockFetch } from '@/test-utils/mockApi'

const server = setupServer(...bagHandlers)
const BASE = MOCK_API_BASE

async function jsonGet(path) {
  const res = await mockFetch(`${BASE}${path}`, { method: 'GET' })
  return { ok: res.ok, status: res.status, body: await res.json() }
}
async function jsonPost(path, body) {
  const res = await mockFetch(`${BASE}${path}`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify(body ?? {}),
  })
  return { ok: res.ok, status: res.status, body: await res.json() }
}

async function retryGet(path, tries = 20) {
  for (let i = 0; i < tries; i += 1) {
    const r = await jsonGet(path)
    if (r.ok) return r
  }
  throw new Error(`get failed: ${path}`)
}
async function retryPost(path, body, tries = 20) {
  for (let i = 0; i < tries; i += 1) {
    const r = await jsonPost(path, body)
    if (r.ok) return r
  }
  throw new Error(`post failed: ${path}`)
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => {
  server.resetHandlers()
  resetBagStore()
})
afterAll(() => server.close())

describe('bag mock — product pricing + marketing (addition §3/§4)', () => {
  it('serves the price breakdown with a derived VAT and tax-inclusive total', async () => {
    const { body } = await retryGet('/bag/product')
    const p = body.data.pricing
    expect(p.priceBeforeTax).toBe(2500)
    expect(p.vatRate).toBe(0.15)
    expect(p.taxAmount).toBe(Math.round(p.priceBeforeTax * p.vatRate))
    expect(p.total).toBe(p.priceBeforeTax + p.taxAmount)
    expect(body.data.availability).toBe('available')
    expect(body.data.marketing.faqs.length).toBeGreaterThan(0)
  })

  it('snapshots the price on the subscription (order keeps its own pricing)', async () => {
    const { body } = await retryGet('/bag/subscription')
    expect(body.data.pricing.total).toBe(2875)
    expect(body.data.fee).toBe(2875)
  })
})

describe('bag mock — assessment context tracks (addition §1/§12)', () => {
  it('exposes the accredited reference + self/advanced context-only tracks on the consultant view', async () => {
    const { body } = await retryGet('/bag/consultant-view')
    const tracks = body.data.tracks
    expect(tracks.reference.kind).toBe('accredited')
    const kinds = tracks.context.map((c) => c.kind)
    expect(kinds).toContain('self')
    expect(kinds).toContain('advanced')
    expect(tracks.context.every((c) => c.kind !== 'accredited')).toBe(true)
  })
})

describe('bag mock — frozen reference snapshot (§8)', () => {
  it('exposes the reference (id + version + frozenAt) on the entity view', async () => {
    const { body } = await retryGet('/bag/entity-view')
    const ref = body.data.reference
    expect(ref).toBeTruthy()
    expect(ref.assessmentId).toBe(BAG_SOURCE_ASSESSMENT_ID)
    expect(ref.version).toBe(1)
    expect(ref.kind).toBe('accredited')
    expect(ref.frozenAt).toBeTruthy()
  })

  it('exposes the same frozen reference on the consultant view', async () => {
    const { body } = await retryGet('/bag/consultant-view')
    expect(body.data.reference.assessmentId).toBe(BAG_SOURCE_ASSESSMENT_ID)
    expect(body.data.reference.version).toBe(1)
  })
})

describe('bag mock — enriched gap model (§8/§11)', () => {
  it('carries priority + status + note + recommended action on the seeded opened gaps', async () => {
    const { body } = await retryGet('/bag/consultant-view')
    const gap = body.data.gaps.find((g) => g.id === 'gap-503')
    expect(gap.priority).toBe('high')
    expect(gap.status).toBe('approved')
    expect(gap.consultantNote).toBeTruthy()
    expect(gap.recommendedAction.ar).toBeTruthy()
    expect(gap.recommendedAction.en).toBeTruthy()
  })

  it('defaults an unworked gap to an approved, unprioritized record', async () => {
    const { body } = await retryGet('/bag/consultant-view')
    const gap = body.data.gaps.find((g) => g.id === 'gap-101')
    expect(gap.priority).toBeNull()
    expect(gap.status).toBe('approved')
    expect(gap.consultantNote).toBeNull()
  })
})

describe('bag mock — consultant gap guidance (§14 builder)', () => {
  it('persists a priority / status / note / action update', async () => {
    const payload = {
      gapId: 'gap-101',
      priority: 'medium',
      status: 'deferred',
      consultantNote: { ar: 'ملاحظة', en: 'note' },
      recommendedAction: { ar: 'إجراء', en: 'action' },
    }
    const saved = await retryPost('/bag/gap-guidance', payload)
    expect(saved.body.data.priority).toBe('medium')
    expect(saved.body.data.status).toBe('deferred')

    const { body } = await retryGet('/bag/consultant-view')
    const gap = body.data.gaps.find((g) => g.id === 'gap-101')
    expect(gap.priority).toBe('medium')
    expect(gap.status).toBe('deferred')
    expect(gap.recommendedAction.en).toBe('action')
  })

  it('rejects guidance for an unknown gap with a 404', async () => {
    let res
    for (let i = 0; i < 20; i += 1) {
      res = await jsonPost('/bag/gap-guidance', { gapId: 'gap-does-not-exist', priority: 'low' })
      if (res.status !== 500) break
    }
    expect(res.status).toBe(404)
  })

  it('NEVER edits the source assessment score via guidance (kit-local only)', async () => {
    const before = await retryGet('/bag/consultant-view')
    const gap503Before = before.body.data.gaps.find((g) => g.id === 'gap-503')
    await retryPost('/bag/gap-guidance', { gapId: 'gap-503', priority: 'low' })
    const after = await retryGet('/bag/consultant-view')
    const gap503After = after.body.data.gaps.find((g) => g.id === 'gap-503')
    expect(gap503After.priority).toBe('low')
    expect(gap503After.current).toBe(gap503Before.current)
    expect(gap503After.target).toBe(gap503Before.target)
    expect(gap503After.gap).toBe(gap503Before.gap)
  })
})

describe('bag mock — re-assessment comparison (§19)', () => {
  it('classifies each criterion outcome and is deterministic', async () => {
    const a = await retryGet('/bag/reassessment')
    const b = await retryGet('/bag/reassessment')
    expect(a.body.data).toEqual(b.body.data)

    const byId = Object.fromEntries(a.body.data.criteria.map((c) => [c.subCriterionId, c]))
    expect(byId[503].outcome).toBe('closed')
    expect(byId[402].outcome).toBe('closed')
    expect(byId[502].outcome).toBe('partially-improved')
    expect(byId[302].outcome).toBe('regressed')
    expect(byId[101].outcome).toBe('stable')
  })

  it('marks the opened stage-1 gaps as in kit scope', async () => {
    const { body } = await retryGet('/bag/reassessment')
    const scoped = body.data.criteria
      .filter((c) => c.inKitScope)
      .map((c) => `gap-${c.subCriterionId}`)
    expect(scoped.sort()).toEqual([...STAGE_1_OPEN_GAP_IDS].sort())
  })

  it('reports a positive overall diff with counts that sum to the criteria total', async () => {
    const { body } = await retryGet('/bag/reassessment')
    const o = body.data.overall
    expect(o.after).toBeGreaterThan(o.before)
    expect(o.afterPercent).toBeGreaterThanOrEqual(o.beforePercent)
    const sum = o.closed + o.improved + o.stable + o.regressed
    expect(sum).toBe(body.data.criteria.length)
    expect(o.regressed).toBeGreaterThanOrEqual(1)
    expect(o.closed).toBeGreaterThanOrEqual(2)
  })

  it('reconciles before/after with each criterion weight (gapAfter = weight − after)', async () => {
    const { body } = await retryGet('/bag/reassessment')
    body.data.criteria.forEach((c) => {
      expect(c.gapAfter).toBe(c.weight - c.after)
      expect(c.diff).toBe(c.after - c.before)
    })
    const fullyCovered = body.data.criteria.filter((c) => c.gapAfter <= 0)
    fullyCovered.forEach((c) => expect(c.outcome).toBe('closed'))
    expect(fullyCovered.length).toBeGreaterThanOrEqual(2)
  })
})
