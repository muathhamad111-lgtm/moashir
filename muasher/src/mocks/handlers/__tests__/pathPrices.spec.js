import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { resetFinanceState, findPayment } from '@/mocks/stores/finance/financeState'
import { findCycle } from '@/mocks/stores/award/cycleStore'
import { discardQueuedJobs, runQueuedJobs } from '@/mocks/stores/mockQueue'
import { announcePaymentPaid } from '@/mocks/stores/finance/paymentHooks'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...handlers)
const SYSTEM_ADMIN = { id: 1, roles: ['system_admin'] }
const SUPER_ADMIN = { id: 900, roles: [], superAdmin: true }
const FINANCE_OFFICER = { id: 22, roles: ['finance_officer'] }
const ENTITY = { id: 6005, roles: ['entity'], entityId: 6005 }
const AWARD_ENTITY = { id: 5001, roles: ['entity'], entityId: 5001 }
const REASON = 'تعديل معتمد في قائمة الأسعار.'
const RUNNING_CYCLE = 31
const KEY = '5f0c2f7a-8f9e-4b6a-9c21-3d4e5f6a7b8c'
const OTHER_KEY = '6a1d3e8b-9a0f-4c7b-8d32-4e5f6a7b8c9d'

async function call(method, path, body, headers = {}) {
  const init = { method, headers: { 'accept-language': 'en', ...headers } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null }
}

function rowOf(body, key) {
  return body.data.rows.find((row) => row.key === key)
}

async function setPrice(key, price, extra = {}) {
  seedMockSession(SYSTEM_ADMIN)
  const { body } = await call('GET', '/settings/prices')
  const base = rowOf(body, key).in_force?.id ?? null
  const result = await call('PUT', `/settings/prices/${key}`, {
    price_before_vat: price,
    availability: 'available',
    base_version_id: base,
    ...extra,
  })
  clearMockSession()
  return result
}

function markPaid(paymentId) {
  const payment = findPayment(paymentId)
  Object.assign(payment, { status: 'paid', paidAt: new Date().toISOString() })
  announcePaymentPaid(payment)
  runQueuedJobs()
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
  discardQueuedJobs()
})
afterAll(() => server.close())

describe('path prices settings', () => {
  it('lists the four rows in order with the in-force version, its quote and the limits', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const { status, body } = await call('GET', '/settings/prices')
    expect(status).toBe(200)
    expect(body.data.rows.map((row) => row.key)).toEqual(['self', 'accredited', 'award', 'bag'])
    expect(rowOf(body, 'self').pricing).toEqual({
      price: null,
      is_free: true,
      availability: 'available',
    })
    expect(rowOf(body, 'accredited').in_force).toMatchObject({
      price_before_vat: '1000.00',
      set_by: null,
      is_scheduled: false,
      quote: { total: '1150.00' },
    })
    expect(Object.keys(body.data.limits).sort()).toEqual([
      'effective_from_max',
      'effective_from_min',
      'price_max',
    ])
  })

  it('appends a version, schedules one, cancels it and keeps the history newest first', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const limits = (await call('GET', '/settings/prices')).body.data.limits
    const saved = await call('PUT', '/settings/prices/accredited', {
      price_before_vat: '1200.00',
      availability: 'available',
      base_version_id: 2,
    })
    expect(saved.status).toBe(200)
    expect(saved.body.meta).toEqual({ changed: true, cancelled_version_id: null })
    expect(saved.body.data.pricing.price.total).toBe('1380.00')
    const scheduled = await call('PUT', '/settings/prices/accredited', {
      price_before_vat: '1500.00',
      availability: 'paused',
      effective_from: limits.effective_from_min,
      base_version_id: saved.body.data.in_force.id,
    })
    expect(scheduled.body.data.scheduled).toMatchObject({
      price_before_vat: '1500.00',
      is_scheduled: true,
      effective_on: limits.effective_from_min,
    })
    const history = await call('GET', '/settings/prices/accredited/history')
    expect(history.body.data.map((item) => item.state)).toEqual([
      'scheduled',
      'in_force',
      'superseded',
    ])
    expect(history.body.data[1].before).toEqual({
      price_before_vat: '1000.00',
      availability: 'available',
    })
    const cancelled = await call('DELETE', '/settings/prices/accredited/scheduled', {})
    expect(cancelled.body.data.scheduled).toBeNull()
    expect((await call('DELETE', '/settings/prices/accredited/scheduled', {})).body.code).toBe(
      'prices.no_scheduled_version',
    )
  })

  it('refuses a stale base version, bad input, unknown rows and the Super Admin without a reason', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const stale = await call('PUT', '/settings/prices/bag', {
      price_before_vat: '2000.00',
      availability: 'available',
      base_version_id: 99,
    })
    expect([stale.status, stale.body.code]).toEqual([409, 'prices.stale_version'])
    expect(stale.body.meta.row.in_force.price_before_vat).toBe('2500.00')
    const invalid = await call('PUT', '/settings/prices/bag', {
      price_before_vat: '20',
      availability: 'gone',
      effective_from: '2001-01-01',
    })
    expect(Object.keys(invalid.body.errors).sort()).toEqual([
      'availability',
      'base_version_id',
      'effective_from',
      'price_before_vat',
    ])
    expect((await call('GET', '/settings/prices/leadership/history')).status).toBe(404)
    seedMockSession(SUPER_ADMIN)
    const body = { price_before_vat: '0.00', availability: 'available', base_version_id: 4 }
    expect((await call('PUT', '/settings/prices/bag', body)).body.code).toBe('reason_required')
    expect((await call('PUT', '/settings/prices/bag', { ...body, reason: REASON })).status).toBe(
      200,
    )
    seedMockSession(FINANCE_OFFICER)
    expect((await call('GET', '/settings/prices')).status).toBe(403)
  })

  it('mirrors the versions in the finance settings and the public prices', async () => {
    await setPrice('bag', '3000.00')
    seedMockSession(SYSTEM_ADMIN)
    expect((await call('GET', '/settings/finance')).body.data.bag_price).toBe('3000.00')
    const prices = await call('GET', '/content/prices')
    expect(prices.body.data.bag.total).toBe('3450.00')
    expect(prices.body.data.paths.award).toEqual({
      price: null,
      is_free: true,
      availability: 'available',
    })
  })
})

describe('paths, free starts and the new purposes', () => {
  it('lists a hidden path only while the entity has something in it', async () => {
    seedMockSession(SYSTEM_ADMIN)
    await call('PUT', '/settings/prices/self', {
      price_before_vat: '0.00',
      availability: 'hidden',
      base_version_id: 1,
    })
    seedMockSession(ENTITY)
    const board = await call('GET', '/dashboard/summary?board=entity')
    const paths = board.body.data.widgets.find((widget) => widget.key === 'paths').data
    expect(paths.self).toMatchObject({ is_listed: false, pricing: { availability: 'hidden' } })
    expect(paths.accredited.is_listed).toBe(true)
    expect((await call('POST', '/assessments', { path: 'self' })).body).toMatchObject({
      code: 'path.unavailable',
      meta: { path: 'self', availability: 'hidden' },
    })
  })

  it('asks for payment before a priced self draft and opens it on the paid order', async () => {
    await setPrice('self', '100.00')
    seedMockSession(ENTITY)
    const start = await call('POST', '/assessments', { path: 'self' })
    expect([start.status, start.body.code]).toEqual([409, 'path.payment_required'])
    expect(start.body.meta).toMatchObject({ path: 'self', purpose: 'self' })
    const order = await call('POST', '/payments', { purpose: 'self' }, { 'idempotency-key': KEY })
    expect(order.status).toBe(201)
    expect(order.body.data).toMatchObject({ purpose: 'self', award_cycle: null })
    expect(order.body.data.pricing.total).toBe('115.00')
    markPaid(order.body.data.id)
    const list = await call('GET', '/assessments?filter[path]=self&filter[status]=draft')
    const draft = list.body.data[0]
    expect(draft).toMatchObject({ status: 'draft', payment: { status: 'paid' } })
    const payment = await call('GET', `/payments/${order.body.data.id}`)
    expect(payment.body.data.request).toMatchObject({ id: draft.id, path: 'self' })
  })

  it('opens a free accredited request directly and refuses it while the path is priced', async () => {
    seedMockSession(ENTITY)
    const priced = await call('POST', '/assessments/accredited')
    expect([priced.status, priced.body.code]).toEqual([409, 'path.payment_required'])
    await setPrice('accredited', '0.00')
    seedMockSession(ENTITY)
    const order = await call(
      'POST',
      '/payments',
      { purpose: 'accredited' },
      { 'idempotency-key': OTHER_KEY },
    )
    expect(order.body).toMatchObject({
      code: 'payment.not_required',
      meta: { purpose: 'accredited' },
    })
    const opened = await call('POST', '/assessments/accredited')
    expect(opened.status).toBe(201)
    expect(opened.body.data).toMatchObject({ path: 'accredited', status: 'draft', payment: null })
    const again = await call('POST', '/assessments/accredited')
    expect(again.body.code).toBe('accredited.request_open')
  })

  it('charges the cycle fee before the award draft and keeps the order on its cycle', async () => {
    findCycle(RUNNING_CYCLE).entryFee = '500.00'
    seedMockSession(AWARD_ENTITY)
    const apply = await call('POST', '/assessments/advanced', { award_cycle_id: RUNNING_CYCLE })
    expect([apply.status, apply.body.code]).toEqual([409, 'path.payment_required'])
    expect(apply.body.meta).toMatchObject({ purpose: 'award', award_cycle_id: RUNNING_CYCLE })
    const missing = await call(
      'POST',
      '/payments',
      { purpose: 'award' },
      { 'idempotency-key': KEY },
    )
    expect(Object.keys(missing.body.errors)).toEqual(['award_cycle_id'])
    const unknown = await call(
      'POST',
      '/payments',
      { purpose: 'award', award_cycle_id: 999 },
      { 'idempotency-key': KEY },
    )
    expect([unknown.status, unknown.body.code]).toEqual([404, 'payment.award_cycle_not_found'])
    const order = await call(
      'POST',
      '/payments',
      { purpose: 'award', award_cycle_id: RUNNING_CYCLE },
      { 'idempotency-key': OTHER_KEY },
    )
    expect(order.status).toBe(201)
    expect(order.body.data.award_cycle.id).toBe(RUNNING_CYCLE)
    expect(order.body.data.pricing.price_before_vat).toBe('500.00')
    const again = await call('POST', '/assessments/advanced', { award_cycle_id: RUNNING_CYCLE })
    expect(again.body.code).toBe('payment.open_order_exists')
    const board = await call('GET', '/dashboard/summary?board=entity')
    const widgets = board.body.data.widgets
    const award = widgets.find((widget) => widget.key === 'paths').data.award
    expect(award.awaiting_payment).toMatchObject({ channel: 'electronic', status: 'created' })
    expect(award.cycle.entry_fee.total).toBe('575.00')
    const next = widgets.find((widget) => widget.key === 'next_step').data
    expect(next).toMatchObject({ rank: 1, key: 'settle_payment', path: 'advanced' })
    expect(next.context).toMatchObject({ payment_channel: 'electronic', payment_status: 'created' })
    expect(next.context.payment_expires_at).toEqual(expect.any(String))
  })

  it('flags an award draft opened by a payment after the window closed', async () => {
    const cycle = findCycle(RUNNING_CYCLE)
    cycle.entryFee = '500.00'
    seedMockSession(AWARD_ENTITY)
    const order = await call(
      'POST',
      '/payments',
      { purpose: 'award', award_cycle_id: RUNNING_CYCLE },
      { 'idempotency-key': KEY },
    )
    cycle.submissionEnd = new Date(Date.now() - 60000).toISOString()
    await call('GET', '/dashboard/summary?board=entity')
    markPaid(order.body.data.id)
    const board = await call('GET', '/dashboard/summary?board=entity')
    const award = board.body.data.widgets.find((widget) => widget.key === 'paths').data.award
    expect(award).toMatchObject({
      window_state: 'closed',
      window_closed_before_payment: true,
      open_request: { status: 'draft', payment: { status: 'paid' } },
    })
  })

  it('locks the cycle entry fee once the submission window opens', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const open = await call('PUT', `/award/cycles/${RUNNING_CYCLE}`, { entry_fee: '100.00' })
    expect([open.status, open.body.code, open.body.meta.locked_fields]).toEqual([
      409,
      'cycle.field_locked',
      ['entry_fee'],
    ])
    const draft = await call('PUT', '/award/cycles/32', { entry_fee: '900.00' })
    expect(draft.body.data).toMatchObject({
      entry_fee: '900.00',
      entry_fee_editable: true,
      entry_fee_quote: { total: '1035.00' },
    })
  })
})
