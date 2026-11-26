import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { resetAssessmentSettings } from '@/mocks/stores/accredited/assessmentSettingsStore'
import { findCycle } from '@/mocks/stores/award/cycleStore'
import { runAwardSchedule } from '@/mocks/stores/award/cycleTransitions'
import { notificationsStore } from '@/mocks/stores/notificationsStore'
import { discardQueuedJobs } from '@/mocks/stores/mockQueue'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...handlers)

const SYSTEM_ADMIN = { id: 1, roles: ['system_admin'] }
const SECRETARY = { id: 18, roles: ['award_secretary'] }
const SUPER_ADMIN = { id: 900, roles: [], superAdmin: true }
const ALBIRR = { id: 5001, roles: ['entity'], entityId: 5001 }
const REASON = 'قرار اللجنة العليا للجائزة بتشغيل أكثر من دورة في الفترة نفسها.'
const DAY = 86400000

async function call(method, path, body) {
  const init = { method, headers: { 'accept-language': 'en' } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null }
}

function useConcurrency(mode) {
  seedMockSession(SYSTEM_ADMIN)
  return call('PUT', '/settings/assessment', { advanced_cycle_concurrency: mode, reason: REASON })
}

function openSecondCycle() {
  Object.assign(findCycle(32), {
    status: 'submission-open',
    submissionStart: new Date(Date.now() - DAY).toISOString(),
    entryFee: '0.00',
  })
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetCriteriaStore()
  resetEntitiesMock()
  resetAssessmentsStore()
  resetAssessmentSettings()
  findCycle(32).initialAssessors.push({ assessorId: 10, isActive: true })
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
  discardQueuedJobs()
  resetAssessmentSettings()
})
afterAll(() => server.close())

describe('award cycle concurrency mock — setting', () => {
  it('defaults to one running cycle and needs a reason to change it', async () => {
    seedMockSession(SYSTEM_ADMIN)
    expect((await call('GET', '/settings/assessment')).body.data.advanced_cycle_concurrency).toBe(
      'single',
    )
    expect(
      (await call('PUT', '/settings/assessment', { advanced_cycle_concurrency: 'concurrent' })).body
        .code,
    ).toBe('reason_required')
    expect(
      (
        await call('PUT', '/settings/assessment', {
          advanced_cycle_concurrency: 'unlimited',
          reason: REASON,
        })
      ).status,
    ).toBe(422)
    const saved = await useConcurrency('staggered')
    expect(saved.body.data.advanced_cycle_concurrency).toBe('staggered')
    seedMockSession(SECRETARY)
    expect((await call('GET', '/settings/assessment')).status).toBe(403)
  })

  it('accepts narrowing while cycles run and applies it to future openings only', async () => {
    await useConcurrency('concurrent')
    openSecondCycle()
    const single = await useConcurrency('single')
    expect(single.status).toBe(200)
    expect(single.body.data).toMatchObject({
      advanced_cycle_concurrency: 'single',
      running_cycles: { running: 2, open_for_submission: 2 },
    })
    expect([findCycle(31).status, findCycle(32).status]).toEqual([
      'submission-open',
      'submission-open',
    ])
    seedMockSession(SUPER_ADMIN)
    expect((await call('POST', '/award/cycles/30/reopen', { reason: REASON })).body.code).toBe(
      'cycle.another_open',
    )
    findCycle(31).status = 'closed'
    findCycle(32).status = 'closed'
    seedMockSession(SUPER_ADMIN)
    expect(
      (await call('POST', '/award/cycles/30/reopen', { reason: REASON })).body.data.status,
    ).toBe('announced')
  })
})

describe('award cycle concurrency mock — opening', () => {
  it('blocks a second running cycle in single mode and reports the block on the draft', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const draft = await call('GET', '/award/cycles/32')
    expect(draft.body.data.open_block).toMatchObject({
      code: 'cycle.another_open',
      concurrency: 'single',
      cycle: { id: 31 },
    })
    expect((await call('POST', '/award/cycles/32/open', {})).body).toMatchObject({
      code: 'cycle.another_open',
      meta: { award_cycle_id: 31, concurrency: 'single' },
    })
  })

  it('opens next to a cycle in committee in staggered mode but not next to an open one', async () => {
    await useConcurrency('staggered')
    expect((await call('POST', '/award/cycles/32/open', {})).body.code).toBe(
      'cycle.another_open_for_submission',
    )
    findCycle(31).status = 'committee-assessment'
    expect((await call('GET', '/award/cycles/32')).body.data.open_block).toBeNull()
    expect((await call('POST', '/award/cycles/32/open', {})).body.data.status).toBe(
      'submission_open',
    )
  })

  it('opens several cycles for submission in concurrent mode and lists them publicly', async () => {
    await useConcurrency('concurrent')
    expect((await call('GET', '/award/cycles/32')).body.data.open_block).toBeNull()
    expect((await call('POST', '/award/cycles/32/open', {})).status).toBe(200)
    clearMockSession()
    const open = await call('GET', '/award/cycles/open')
    expect(open.body.data.map((cycle) => cycle.id)).toEqual([32, 31])
    expect((await call('GET', '/award/cycles/current')).body.data.id).toBe(32)
  })

  it('reopens a closed cycle next to a running one only when the setting allows it', async () => {
    seedMockSession(SUPER_ADMIN)
    expect((await call('POST', '/award/cycles/30/reopen', { reason: REASON })).body.code).toBe(
      'cycle.another_open',
    )
    await useConcurrency('staggered')
    seedMockSession(SUPER_ADMIN)
    expect(
      (await call('POST', '/award/cycles/30/reopen', { reason: REASON })).body.data.status,
    ).toBe('announced')
  })

  it('keeps a due draft closed on schedule in single mode and tells the staff once a day', () => {
    Object.assign(findCycle(32), {
      submissionStart: new Date(Date.now() - DAY).toISOString(),
    })
    const deferred = () => notificationsStore.filter((item) => item.code === 'CYC-04').length
    const before = deferred()
    runAwardSchedule()
    runAwardSchedule()
    expect(findCycle(32).status).toBe('draft')
    expect(deferred() - before).toBe(2)
  })
})

describe('award cycle concurrency mock — entity', () => {
  it('serves every open cycle with its own eligibility and the chosen cycle on request', async () => {
    await useConcurrency('concurrent')
    openSecondCycle()
    seedMockSession(ALBIRR)
    const current = (await call('GET', '/assessments/advanced/current')).body.data
    expect(current.cycle.id).toBe(32)
    expect(current.open_cycles.map((cycle) => cycle.id)).toEqual([32, 31])
    expect(current.open_cycles[0]).toMatchObject({
      eligibility: { eligible: true, reason: null },
      request: null,
      entry_fee: { total: expect.any(String) },
    })
    const chosen = await call('GET', '/assessments/advanced/current?award_cycle_id=31')
    expect(chosen.body.data.cycle.id).toBe(31)
    expect((await call('GET', '/assessments/advanced/current?award_cycle_id=abc')).status).toBe(422)
  })

  it('lets the entity apply to two concurrent cycles with one request in each', async () => {
    await useConcurrency('concurrent')
    openSecondCycle()
    seedMockSession(ALBIRR)
    const first = await call('POST', '/assessments/advanced', { award_cycle_id: 31 })
    const second = await call('POST', '/assessments/advanced', { award_cycle_id: 32 })
    expect([first.status, second.status]).toEqual([201, 201])
    expect(first.body.data.id).not.toBe(second.body.data.id)
    expect((await call('POST', '/assessments/advanced', { award_cycle_id: 32 })).body.data.id).toBe(
      second.body.data.id,
    )
    const board = (await call('GET', '/dashboard/summary?board=entity')).body.data
    const paths = board.widgets.find((widget) => widget.key === 'paths').data
    expect(paths.award.open_cycles.map((cycle) => cycle.eligibility.reason)).toEqual([
      'request_exists',
      'request_exists',
    ])
  })
})
