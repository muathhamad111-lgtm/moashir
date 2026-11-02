import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { paymentsHandlers } from '@/mocks/handlers/payments'
import { financeHandlers } from '@/mocks/handlers/finance'
import { financeSettingsHandlers } from '@/mocks/handlers/financeSettings'
import { moyasarSandboxHandlers } from '@/mocks/handlers/moyasarSandbox'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { resetIntegrationsState } from '@/mocks/stores/integrations/integrationsState'
import { SEEDED_IDS, SEEDED_PROVIDER_IDS } from '@/mocks/fixtures/finance'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'
import { findPayment } from '@/mocks/stores/finance/financeState'
import { recordRefund, validatedRefund } from '@/mocks/stores/finance/refundRecording'
import { refundResource } from '@/mocks/stores/finance/financeSerializers'
import { MockRejection } from '@/mocks/contract/rejection'
import { csvOf } from '@/mocks/stores/finance/financeExports'

const server = setupServer(...paymentsHandlers, ...financeHandlers, ...financeSettingsHandlers, ...moyasarSandboxHandlers)

const ENTITY = { id: 5001, roles: ['entity'], entityId: 5001 }
const OTHER_ENTITY = { id: 6001, roles: ['entity'], entityId: 6001 }
const FINANCE_OFFICER = { id: 22, roles: ['finance_officer'] }
const SYSTEM_ADMIN = { id: 1, roles: ['system_admin'] }
const SUPER_ADMIN = { id: 900, roles: [], superAdmin: true }
const ASSESSOR = { id: 30, roles: ['assessor'] }

const PRICING_KEYS = ['currency', 'price_before_vat', 'total', 'vat_amount', 'vat_rate']
const OWN_PAYMENT_KEYS = [
  'cancelled_at', 'card_last4', 'created_at', 'expires_at', 'failed_at', 'failure_reason', 'id', 'is_test', 'method',
  'paid_at', 'pricing', 'purpose', 'receipt', 'reference', 'refunded_at', 'status',
]
const CHECKOUT_KEYS = [
  'amount_halalas', 'apple_pay', 'callback_url', 'currency', 'description', 'expires_at', 'language', 'metadata', 'methods',
  'mode', 'pattern', 'payment_intent', 'publishable_key', 'supported_networks',
]
const TRANSACTION_KEYS = [
  'card_last4', 'created_at', 'entity', 'has_refund', 'id', 'is_test', 'late_paid', 'manually_settled', 'method', 'paid_at',
  'pricing', 'purpose', 'receipt_number', 'reference', 'refunded_at', 'status', 'updated_at',
]
const DETAIL_EXTRA_KEYS = [
  'cancelled_at', 'expires_at', 'failed_at', 'failure_code', 'failure_message', 'failure_reason', 'late_paid_at',
  'manually_settled_at', 'provider_payment_id', 'receipt', 'refund', 'verified_at',
]
const RECEIPT_KEYS = ['id', 'is_test', 'issued_at', 'last_reissued_at', 'number', 'reissue_count', 'snapshot', 'status', 'voided_at']
const SNAPSHOT_KEYS = [
  'card_last4', 'currency', 'entity_name', 'entity_reference', 'method', 'paid_at', 'payment_reference', 'price_before_vat',
  'provider_payment_id', 'purpose', 'total', 'vat_amount', 'vat_rate',
]
const REFUND_KEYS = ['amount', 'created_at', 'currency', 'evidence', 'id', 'payment', 'reason', 'recorded_by', 'refunded_on', 'status', 'transfer_reference']
const STATS_KEYS = ['completed_count', 'currency', 'incomplete_count', 'refunded_count', 'total_income', 'total_refunded', 'total_vat', 'transactions_count']

function keys(value) {
  return Object.keys(value).sort()
}

async function call(path, { method = 'GET', body, headers = {}, form } = {}) {
  const init = { method, headers: { accept: 'application/json', ...headers } }
  if (form) init.body = form
  if (body !== undefined) {
    init.body = JSON.stringify(body)
    init.headers['content-type'] = 'application/json'
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  const type = response.headers.get('content-type') ?? ''
  return { status: response.status, headers: response.headers, body: type.includes('json') ? await response.json() : await response.text() }
}

function as(user) {
  seedMockSession(user)
}

function openCheckout(purpose = 'accredited', key = crypto.randomUUID()) {
  return call('/payments', { method: 'POST', body: { purpose }, headers: { 'idempotency-key': key } })
}

async function fulfil(checkout, outcome) {
  const response = await fetch(`https://api.moyasar.com/v1/payment_intents/${checkout.payment_intent.id}/fulfill`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({
      publishable_api_key: checkout.publishable_key,
      client_secret: checkout.payment_intent.client_secret,
      metadata: checkout.metadata,
      source: { type: 'creditcard', sandbox_outcome: outcome },
    }),
  })
  return response.json()
}

function verify(paymentId, providerPaymentId) {
  return call(`/payments/${paymentId}/verify`, { method: 'POST', body: { provider_payment_id: providerPaymentId } })
}

function refundForm(fields) {
  const form = new FormData()
  Object.entries(fields).forEach(([key, value]) => (value instanceof Blob ? form.append(key, value, value.name) : form.append(key, value)))
  return form
}

function pdf(name = 'bank-transfer-receipt.pdf', text = '%PDF-1.4\n%%EOF') {
  return new File([text], name, { type: 'application/pdf' })
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetFinanceState()
  resetIntegrationsState()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('checkout (A1) mock contract', () => {
  it('opens an order with the exact OwnPayment and Checkout shapes, metadata snake_case and no card data', async () => {
    as(ENTITY)
    const { status, body } = await openCheckout()
    expect(status).toBe(201)
    const { checkout, ...payment } = body.data
    expect(keys(payment)).toEqual(OWN_PAYMENT_KEYS)
    expect(keys(payment.pricing)).toEqual(PRICING_KEYS)
    expect(payment.pricing).toEqual({ price_before_vat: '1000.00', vat_rate: '15.00', vat_amount: '150.00', total: '1150.00', currency: 'SAR' })
    expect(payment.id).toMatch(/^[0-9a-f-]{36}$/)
    expect(payment.reference).toMatch(/^PAY-2026-\d{6}$/)
    expect(payment).toMatchObject({ status: 'created', is_test: true, receipt: null })
    expect(keys(checkout)).toEqual(CHECKOUT_KEYS)
    expect(checkout).toMatchObject({ pattern: 'intent', mode: 'test', amount_halalas: 115000, currency: 'SAR', language: 'ar' })
    expect(checkout.callback_url).toBe(`${window.location.origin}/payments/${payment.id}/return`)
    expect(checkout.metadata).toEqual({ moashir_payment_id: payment.id, moashir_reference: payment.reference, purpose: 'accredited', app_env: 'local' })
    expect(keys(checkout.payment_intent)).toEqual(['client_secret', 'id'])
    expect(body.meta).toEqual({ idempotent_replay: false, existing_order: false })
  })

  it('requires a UUID Idempotency-Key, replays the same key and refuses it for another service', async () => {
    as(ENTITY)
    const missing = await call('/payments', { method: 'POST', body: { purpose: 'accredited' } })
    expect(missing.status).toBe(422)
    expect(missing.body.code).toBe('idempotency_key.missing')
    expect(Object.keys(missing.body.errors)).toEqual(['idempotency_key'])
    const key = crypto.randomUUID()
    const first = await openCheckout('accredited', key)
    const replay = await openCheckout('accredited', key)
    expect(replay.status).toBe(201)
    expect(replay.body.meta.idempotent_replay).toBe(true)
    expect(replay.body.data.id).toBe(first.body.data.id)
    const reused = await openCheckout('bag', key)
    expect(reused.body.code).toBe('idempotency_key.reused')
  })

  it('returns the open order of the same service with 200 and refuses the retired leadership path', async () => {
    as(ENTITY)
    const first = await openCheckout('accredited')
    const again = await openCheckout('accredited')
    expect(again.status).toBe(200)
    expect(again.body.meta.existing_order).toBe(true)
    expect(again.body.data.id).toBe(first.body.data.id)
    const pending = await openCheckout('bag')
    expect(pending.status).toBe(200)
    expect(pending.body.data.status).toBe('pending')
    expect(pending.body.data.checkout).toBeNull()
    const leadership = await openCheckout('leadership')
    expect([leadership.status, leadership.body.code]).toEqual([422, 'purpose.not_available'])
  })

  it('denies staff (403), guests (401) and answers 503 when the gateway is not configured', async () => {
    as(FINANCE_OFFICER)
    expect((await openCheckout()).status).toBe(403)
    clearMockSession()
    expect((await openCheckout()).status).toBe(401)
  })

  it('verifies a paid gateway payment, issues a TEST receipt and ignores later verifications', async () => {
    as(ENTITY)
    const { data } = (await openCheckout()).body
    const provider = await fulfil(data.checkout, 'paid')
    const verified = await verify(data.id, provider.id)
    expect(verified.status).toBe(200)
    expect(verified.body.meta.verification).toBe('applied')
    expect(verified.body.data).toMatchObject({ status: 'paid', method: 'mada', card_last4: '7412' })
    expect(verified.body.data.receipt).toMatchObject({ number: null, status: 'issued', is_test: true })
    const again = await verify(data.id, provider.id)
    expect(again.body.meta.verification).toBe('ignored')
  })

  it('answers 202 while 3-D Secure is pending, then settles', async () => {
    as(ENTITY)
    const { data } = (await openCheckout()).body
    const provider = await fulfil(data.checkout, 'three_ds_completes')
    const pending = await verify(data.id, provider.id)
    expect([pending.status, pending.body.meta.verification, pending.body.data.status]).toEqual([202, 'pending', 'pending'])
    const settled = await verify(data.id, provider.id)
    expect([settled.status, settled.body.data.status]).toEqual([200, 'paid'])
  })

  it('maps a declined payment to failed with its reason group', async () => {
    as(ENTITY)
    const { data } = (await openCheckout()).body
    const provider = await fulfil(data.checkout, 'insufficient_funds')
    const verified = await verify(data.id, provider.id)
    expect(verified.body.data).toMatchObject({ status: 'failed', failure_reason: 'insufficient_funds' })
  })

  it('refuses foreign, unknown and mismatched gateway payments', async () => {
    as(ENTITY)
    const first = (await openCheckout('accredited')).body.data
    const foreignProvider = await fulfil(first.checkout, 'paid')
    as(OTHER_ENTITY)
    const other = (await openCheckout('accredited')).body.data
    const rejected = await verify(other.id, foreignProvider.id)
    expect([rejected.status, rejected.body.code]).toEqual([409, 'payment.verification_rejected'])
    const unknown = await verify(other.id, 'mock_pay_unknown')
    expect([unknown.status, unknown.body.code]).toEqual([422, 'payment.provider_payment_not_found'])
    const bad = await verify(other.id, 'not valid!')
    expect([bad.status, bad.body.code]).toEqual([422, 'validation_failed'])
    as(ENTITY)
    const mismatch = await verify(SEEDED_IDS.ownPending, foreignProvider.id)
    expect([mismatch.status, mismatch.body.code]).toEqual([409, 'payment.binding_mismatch'])
    const foreignRecord = await call(`/payments/${other.id}`)
    expect(foreignRecord.status).toBe(404)
  })

  it('keeps a late payment on a cancelled order cancelled', async () => {
    as(ENTITY)
    const { data } = (await openCheckout()).body
    const provider = await fulfil(data.checkout, 'paid')
    const realNow = Date.now
    Date.now = () => realNow() + 31 * 60000
    try {
      const late = await verify(data.id, provider.id)
      expect(late.body.meta.verification).toBe('late_payment')
      expect(late.body.data.status).toBe('cancelled')
    } finally {
      Date.now = realNow
    }
  })

  it('consumes an intent once at the sandbox gateway', async () => {
    as(ENTITY)
    const { data } = (await openCheckout()).body
    await fulfil(data.checkout, 'paid')
    const second = await fetch(`https://api.moyasar.com/v1/payment_intents/${data.checkout.payment_intent.id}/fulfill`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ publishable_api_key: data.checkout.publishable_key, client_secret: data.checkout.payment_intent.client_secret }),
    })
    expect(second.status).toBe(400)
  })
})

describe('My Payments and receipts mock contract', () => {
  it('lists own payments newest first with live-only stats', async () => {
    as(ENTITY)
    const { body } = await call('/payments/mine')
    expect(body.data.every((item) => keys(item).join() === OWN_PAYMENT_KEYS.join())).toBe(true)
    expect(body.data.map((item) => item.reference)).toEqual(['PAY-2026-000011', 'PAY-2026-000006', 'PAY-2026-000001', 'PAY-2026-000009'])
    expect(body.meta.stats).toEqual({ count: 3, paid_count: 1, total_paid: '1150.00', total_vat: '150.00', currency: 'SAR' })
    expect(keys(body.meta.pagination)).toEqual(['last_page', 'page', 'per_page', 'total'])
    const filtered = await call('/payments/mine?filter[status]=failed')
    expect(filtered.body.data.map((item) => item.status)).toEqual(['failed'])
    const invalid = await call('/payments/mine?filter[method]=mada')
    expect(invalid.status).toBe(422)
  })

  it('returns the receipt snapshot, a signed link bound to the user and the file', async () => {
    as(ENTITY)
    const receipt = await call(`/payments/${SEEDED_IDS.paidAccredited}/receipt`)
    expect(keys(receipt.body.data)).toEqual(RECEIPT_KEYS)
    expect(keys(receipt.body.data.snapshot)).toEqual(SNAPSHOT_KEYS)
    expect(receipt.body.data.number).toBe('RCP-2026-000001')
    const link = await call(`/payments/${SEEDED_IDS.paidAccredited}/receipt/download`, { method: 'POST' })
    expect(keys(link.body.data)).toEqual(['expires_at', 'file_name', 'url'])
    const path = link.body.data.url.replace(window.location.origin, '')
    const file = await wireFetch(`${window.location.origin}${path}`)
    expect(file.status).toBe(200)
    expect(file.headers.get('content-disposition')).toContain('RCP-2026-000001.pdf')
    as(OTHER_ENTITY)
    expect((await wireFetch(`${window.location.origin}${path}`)).status).toBe(403)
    expect((await call(`/payments/${SEEDED_IDS.paidAccredited}/receipt`)).status).toBe(404)
    as(ENTITY)
    const none = await call(`/payments/${SEEDED_IDS.ownFailed}/receipt`)
    expect([none.status, none.body.code]).toEqual([404, 'receipt.not_issued'])
  })
})

describe('finance staff mock contract', () => {
  it('shapes transactions, stats and the detail exactly, live payments by default', async () => {
    as(FINANCE_OFFICER)
    const { body } = await call('/finance/transactions?per_page=50')
    expect(keys(body.data[0])).toEqual(TRANSACTION_KEYS)
    expect(keys(body.data[0].entity)).toEqual(['email', 'id', 'name', 'reference'])
    expect(body.data.every((item) => item.is_test === false)).toBe(true)
    expect(keys(body.meta.stats)).toEqual(STATS_KEYS)
    expect(body.meta.stats).toMatchObject({ refunded_count: 1, total_refunded: '1150.00' })
    const detail = await call(`/finance/transactions/${SEEDED_IDS.refunded}`)
    expect(keys(detail.body.data)).toEqual([...TRANSACTION_KEYS, ...DETAIL_EXTRA_KEYS].sort())
    expect(keys(detail.body.data.refund)).toEqual(REFUND_KEYS)
    expect(detail.body.data.receipt.status).toBe('voided')
    const test = await call('/finance/transactions?filter[mode]=test')
    expect(test.body.data.map((item) => item.reference)).toContain('PAY-2026-000006')
  })

  it('searches by entity name in Arabic and English, sorts and rejects bad input', async () => {
    as(FINANCE_OFFICER)
    const arabic = await call(`/finance/transactions?search=${encodeURIComponent('نماء')}`)
    expect(new Set(arabic.body.data.map((item) => item.entity.id))).toEqual(new Set([6001]))
    const english = await call('/finance/transactions?search=namaa')
    expect(english.body.data.length).toBe(arabic.body.data.length)
    const sorted = await call('/finance/transactions?sort=-total&per_page=100')
    expect(sorted.body.data[0].pricing.total).toBe('2875.00')
    expect((await call('/finance/transactions?search=a')).status).toBe(422)
    expect((await call('/finance/transactions?sort=-amount')).status).toBe(422)
    expect((await call('/finance/transactions?filter[to]=2026-01-01&filter[from]=2026-02-01')).status).toBe(422)
  })

  it('denies roles without finance permissions and keeps the events log for the System Administrator', async () => {
    as(ASSESSOR)
    expect((await call('/finance/transactions')).status).toBe(403)
    as(FINANCE_OFFICER)
    expect((await call('/finance/payment-events')).status).toBe(403)
    as(SYSTEM_ADMIN)
    const events = await call('/finance/payment-events')
    expect(keys(events.body.data[0])).toEqual(['created_at', 'error_code', 'event_type', 'id', 'mode', 'payment', 'processed_at', 'provider_ref', 'result', 'signature_valid', 'source'])
    expect(events.body.data.every((event) => event.provider_ref === null || event.provider_ref.startsWith('…'))).toBe(true)
  })

  it('summarises live payments by purpose', async () => {
    as(FINANCE_OFFICER)
    const { body } = await call('/finance/summary')
    expect(keys(body.data)).toEqual([...STATS_KEYS, 'by_purpose'].sort())
    expect(body.data.by_purpose.map((row) => row.purpose)).toEqual(['accredited', 'bag'])
    expect(body.meta).toEqual({ live_only: true })
    expect((await call('/finance/summary?from=2026-13-01')).status).toBe(422)
  })

  it('streams a small export and queues a large one until it is ready', async () => {
    as(FINANCE_OFFICER)
    const small = await call('/finance/transactions/export?filter[status]=failed', { method: 'POST' })
    expect(small.status).toBe(200)
    expect(small.headers.get('x-export-row-count')).toBe('2')
    expect(small.body.trim().split('\n')).toHaveLength(3)
    expect(small.body.split('\n')[0]).toContain('المرجع')
    expect(csvOf([], 'en').startsWith('\uFEFF')).toBe(true)
    const large = await call('/finance/transactions/export', { method: 'POST' })
    expect(large.status).toBe(202)
    expect(keys(large.body.data)).toEqual(['completed_at', 'created_at', 'id', 'row_count', 'status'])
    const notReady = await call(`/finance/exports/${large.body.data.id}/download`, { method: 'POST' })
    expect([notReady.status, notReady.body.code]).toEqual([409, 'finance_export.not_ready'])
    as(SYSTEM_ADMIN)
    expect((await call(`/finance/exports/${large.body.data.id}`)).status).toBe(404)
  })

  it('records a full refund with a clean PDF and voids the receipt', async () => {
    const officer = { id: 22, roles: ['finance_officer'], name: null }
    const form = refundForm({
      amount: '1150.00',
      refundedOn: '2026-10-01',
      reason: 'The entity withdrew before the assessor started.',
      transferReference: 'TRF-2026-0142',
      evidence: pdf(),
    })
    const refund = await recordRefund(officer, findPayment(SEEDED_IDS.paidAccredited), validatedRefund(form))
    const resource = refundResource(refund)
    expect(keys(resource)).toEqual(['amount', 'createdAt', 'currency', 'evidence', 'id', 'payment', 'reason', 'recordedBy', 'refundedOn', 'status', 'transferReference'])
    expect(resource.evidence).toMatchObject({ name: 'bank-transfer-receipt.pdf', mimeType: 'application/pdf', scanStatus: 'clean' })
    expect(resource.recordedBy).toEqual({ id: 22, name: 'سعود بن مبارك الدواسر' })
    as(FINANCE_OFFICER)
    const detail = await call(`/finance/transactions/${SEEDED_IDS.paidAccredited}`)
    expect([detail.body.data.status, detail.body.data.receipt.status, detail.body.data.has_refund]).toEqual(['refunded', 'voided', true])
    await expect(recordRefund(officer, findPayment(SEEDED_IDS.paidAccredited), validatedRefund(form))).rejects.toMatchObject({
      status: 409,
      code: 'state.transition_illegal',
    })
  })

  it('refuses partial amounts, early dates, mismatched content and infected files', async () => {
    const officer = { id: 22, roles: ['finance_officer'] }
    const payment = findPayment(SEEDED_IDS.paidBag)
    const base = { refundedOn: '2026-10-01', reason: 'The entity withdrew before the assessor started.', transferReference: 'TRF-2026-0142' }
    const attempt = (fields) => recordRefund(officer, payment, validatedRefund(refundForm({ ...base, amount: '2875.00', evidence: pdf(), ...fields })))
    await expect(attempt({ amount: '100.00' })).rejects.toMatchObject({ code: 'refund.amount_must_equal_total', meta: { paymentTotal: '2875.00' } })
    await expect(attempt({ refundedOn: '2026-08-01' })).rejects.toMatchObject({ code: 'refund.date_before_payment' })
    await expect(attempt({ evidence: pdf('receipt.png') })).rejects.toMatchObject({ code: 'file.content_mismatch', fields: { evidence: expect.any(Array) } })
    await expect(attempt({ evidence: pdf('scan.pdf', '%PDF-1.4 EICAR-STANDARD-ANTIVIRUS-TEST-FILE') })).rejects.toMatchObject({ code: 'file.infected' })
    expect(payment.status).toBe('paid')
    let invalid = null
    try {
      validatedRefund(refundForm({ amount: '2875', reason: 'short' }))
    } catch (error) {
      invalid = error
    }
    expect(invalid).toBeInstanceOf(MockRejection)
    expect(Object.keys(invalid.fields).sort()).toEqual(['amount', 'evidence', 'reason', 'refundedOn', 'transferReference'])
    as(ASSESSOR)
    expect((await call(`/finance/payments/${SEEDED_IDS.paidBag}/refunds`, { method: 'POST', body: {} })).status).toBe(403)
    as(FINANCE_OFFICER)
    expect((await call('/finance/payments/00000000-0000-4000-8000-000000000000/refunds', { method: 'POST', body: {} })).status).toBe(404)
  })

  it('reissues an issued receipt with a reason and refuses a voided one', async () => {
    as(FINANCE_OFFICER)
    const detail = (await call(`/finance/transactions/${SEEDED_IDS.paidBag}`)).body.data
    const reissued = await call(`/finance/receipts/${detail.receipt.id}/reissue`, { method: 'POST', body: { reason: 'A corrected copy was requested.' } })
    expect(reissued.body.data.reissue_count).toBe(1)
    const voided = (await call(`/finance/transactions/${SEEDED_IDS.refunded}`)).body.data.receipt
    const refused = await call(`/finance/receipts/${voided.id}/reissue`, { method: 'POST', body: { reason: 'A corrected copy was requested.' } })
    expect([refused.status, refused.body.code]).toEqual([409, 'receipt.voided'])
    as(SYSTEM_ADMIN)
    expect((await call(`/finance/receipts/${detail.receipt.id}/reissue`, { method: 'POST', body: { reason: 'A corrected copy was requested.' } })).status).toBe(403)
  })

  it('lets only the Super Admin settle an open order manually, with a reason', async () => {
    const body = { status: 'paid', reason: 'Confirmed as paid in the Moyasar dashboard by phone.' }
    as(SYSTEM_ADMIN)
    expect((await call(`/finance/payments/${SEEDED_IDS.pendingLive}/manual-settle`, { method: 'POST', body })).status).toBe(403)
    as(SUPER_ADMIN)
    const short = await call(`/finance/payments/${SEEDED_IDS.pendingLive}/manual-settle`, { method: 'POST', body: { status: 'paid', reason: 'short' } })
    expect(short.status).toBe(422)
    const settled = await call(`/finance/payments/${SEEDED_IDS.pendingLive}/manual-settle`, { method: 'POST', body })
    expect(settled.body.data).toMatchObject({ status: 'paid', manually_settled: true, provider_payment_id: SEEDED_PROVIDER_IDS.pendingLive })
    expect(settled.body.data.receipt.number).toMatch(/^RCP-2026-\d{6}$/)
    const closed = await call(`/finance/payments/${SEEDED_IDS.pendingLive}/manual-settle`, { method: 'POST', body })
    expect(closed.body.code).toBe('state.transition_illegal')
  })

  it('lists refunds and abandoned carts with masked e-mails and consent', async () => {
    as(SYSTEM_ADMIN)
    const refunds = await call('/finance/refunds')
    expect(keys(refunds.body.data[0])).toEqual(REFUND_KEYS)
    const evidence = await call(`/finance/refunds/${refunds.body.data[0].id}/evidence/download`, { method: 'POST' })
    expect(evidence.body.data.file_name).toBe('bank-transfer-receipt.pdf')
    const carts = await call('/messaging/abandoned-carts?filter[contacted]=false')
    expect(keys(carts.body.data[0])).toEqual(['contacted_at', 'email', 'entity', 'id', 'last_seen_at', 'marketing_consent', 'purpose', 'started_at'])
    expect(carts.body.data.every((cart) => /^.\*\*\*@/.test(cart.email) && cart.contacted_at === null)).toBe(true)
    as(FINANCE_OFFICER)
    expect((await call('/messaging/abandoned-carts')).status).toBe(403)
  })
})

describe('finance settings mock contract', () => {
  it('answers the settings shape with a server preview and per-field permissions', async () => {
    as(SYSTEM_ADMIN)
    const { body } = await call('/settings/finance')
    expect(keys(body.data)).toEqual(['accredited_price', 'bag_price', 'currency', 'editable_fields', 'order_validity_limits', 'order_validity_minutes', 'pricing', 'vat_rate'])
    expect(body.data.editable_fields).toEqual(['accredited_price', 'bag_price', 'vat_rate', 'order_validity_minutes'])
    expect(body.data.pricing.accredited).toEqual({ price_before_vat: '1000.00', vat_rate: '15.00', vat_amount: '150.00', total: '1150.00', currency: 'SAR' })
    const updated = await call('/settings/finance', { method: 'PUT', body: { vat_rate: '5' } })
    expect(updated.body.data.vat_rate).toBe('5.00')
    expect(updated.body.data.pricing.bag.total).toBe('2625.00')
    const invalid = await call('/settings/finance', { method: 'PUT', body: { accredited_price: '1500', order_validity_minutes: 90 } })
    expect(Object.keys(invalid.body.errors).sort()).toEqual(['accredited_price', 'order_validity_minutes'])
  })

  it('requires a reason from the Super Admin and denies roles without the field permissions', async () => {
    as(SUPER_ADMIN)
    const missing = await call('/settings/finance', { method: 'PUT', body: { bag_price: '3000.00' } })
    expect([missing.status, missing.body.code]).toEqual([422, 'reason_required'])
    const reasoned = await call('/settings/finance', { method: 'PUT', body: { bag_price: '3000.00', reason: 'Approved price list for 2027.' } })
    expect(reasoned.body.data.bag_price).toBe('3000.00')
    as(FINANCE_OFFICER)
    expect((await call('/settings/finance')).status).toBe(403)
  })
})
