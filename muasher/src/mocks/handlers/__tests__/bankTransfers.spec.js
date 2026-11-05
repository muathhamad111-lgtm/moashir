import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { setupServer } from 'msw/node'
import { paymentsHandlers } from '@/mocks/handlers/payments'
import { bankTransfersHandlers } from '@/mocks/handlers/bankTransfers'
import { paymentChannelsHandlers } from '@/mocks/handlers/paymentChannels'
import { dashboardHandlers } from '@/mocks/handlers/dashboard'
import { financeState, findPayment, resetFinanceState } from '@/mocks/stores/finance/financeState'
import { resetIntegrationsState } from '@/mocks/stores/integrations/integrationsState'
import { BANK_TRANSFER_IDS, EXAMPLE_ACCOUNT_ID } from '@/mocks/fixtures/bankTransfers'
import {
  acceptProof,
  openTransfer,
  validatedTransfer,
} from '@/mocks/stores/finance/bankTransferSubmission'
import { MockRejection } from '@/mocks/contract/rejection'
import { findEntity } from '@/mocks/stores/entitiesStore'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(
  ...bankTransfersHandlers,
  ...paymentChannelsHandlers,
  ...paymentsHandlers,
  ...dashboardHandlers,
)

const ADMIN = { id: 2, roles: ['system_admin'] }
const OFFICER = { id: 22, roles: ['finance_officer'] }
const SUPER_ADMIN = { id: 900, roles: [], superAdmin: true }
const ENTITY = { id: 5001, roles: ['entity'], entityId: 5001 }
const VALID_IBAN = 'SA53 0000 0000 0012 3456 7890'
const REASON = 'Verified against the bank statement of the fees account.'

async function call(path, { method = 'GET', body, headers = {} } = {}) {
  const init = { method, headers: { accept: 'application/json', ...headers } }
  if (body !== undefined) {
    init.body = JSON.stringify(body)
    init.headers['content-type'] = 'application/json'
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null }
}

function newAccount(iban = VALID_IBAN) {
  return {
    bank_name: { ar: 'مصرف العيّنة', en: 'Sample Bank' },
    account_holder_name: { ar: 'منصة مؤشر — حساب الرسوم', en: 'Moashir Platform — fees account' },
    iban,
  }
}

function transferForm(fields) {
  const form = new FormData()
  Object.entries(fields).forEach(([key, value]) =>
    value instanceof Blob ? form.append(key, value, value.name) : form.append(key, value),
  )
  return form
}

function pdf(text = '%PDF-1.4\n%%EOF') {
  return new File([text], 'bank-transfer-receipt.pdf', { type: 'application/pdf' })
}

async function rejectionOf(promise) {
  try {
    await promise
    return null
  } catch (error) {
    if (!(error instanceof MockRejection)) throw error
    return error
  }
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetFinanceState()
  resetIntegrationsState()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
  vi.useRealTimers()
})
afterAll(() => server.close())

describe('payment channels and bank accounts mock contract', () => {
  it('serves the channels view and refuses users without the payment settings permission', async () => {
    seedMockSession(ADMIN)
    const { status, body } = await call('/settings/payment/channels')
    expect(status).toBe(200)
    expect(body.data).toEqual({
      channels: ['electronic', 'bank_transfer'],
      available_channels: ['electronic', 'bank_transfer'],
      gateway_ready: true,
      active_bank_accounts_count: 1,
      reviewers_count: expect.any(Number),
    })
    seedMockSession(OFFICER)
    expect((await call('/settings/payment/channels')).status).toBe(403)
  })

  it('validates the channel set and asks the Super Admin for a reason', async () => {
    seedMockSession(ADMIN)
    const empty = await call('/settings/payment/channels', {
      method: 'PUT',
      body: { channels: [] },
    })
    expect([empty.status, empty.body.code]).toEqual([422, 'validation_failed'])
    const saved = await call('/settings/payment/channels', {
      method: 'PUT',
      body: { channels: ['bank_transfer'] },
    })
    expect(saved.body.data.channels).toEqual(['bank_transfer'])
    seedMockSession(SUPER_ADMIN)
    const missing = await call('/settings/payment/channels', {
      method: 'PUT',
      body: { channels: ['electronic'] },
    })
    expect([missing.status, missing.body.code]).toEqual([422, 'reason_required'])
  })

  it('creates an account with a valid IBAN and refuses a wrong check digit or a duplicate', async () => {
    seedMockSession(ADMIN)
    const created = await call('/settings/payment/bank-accounts', {
      method: 'POST',
      body: newAccount(),
    })
    expect(created.status).toBe(201)
    expect(created.body.data).toMatchObject({
      iban: 'SA5300000000001234567890',
      is_active: true,
      is_used: false,
      display_order: 2,
    })
    const checksum = await call('/settings/payment/bank-accounts', {
      method: 'POST',
      body: newAccount('SA0380000000608010167518'),
    })
    expect(checksum.status).toBe(422)
    expect(Object.keys(checksum.body.errors)).toEqual(['iban'])
    const duplicate = await call('/settings/payment/bank-accounts', {
      method: 'POST',
      body: newAccount('sa03 8000 0000 6080 1016 7519'),
    })
    expect(duplicate.body.errors.iban[0]).toMatch(/مسجّل|already/)
  })

  it('blocks deleting an account while transfers wait, listing them, then archives it', async () => {
    seedMockSession(ADMIN)
    await call('/settings/payment/bank-accounts', { method: 'POST', body: newAccount() })
    const blocked = await call(`/settings/payment/bank-accounts/${EXAMPLE_ACCOUNT_ID}`, {
      method: 'DELETE',
    })
    expect([blocked.status, blocked.body.code]).toEqual([409, 'bank_account.pending_transfers'])
    expect(blocked.body.meta.transfers.map((item) => item.reference)).toEqual([
      'PAY-2026-000021',
      'PAY-2026-000022',
      'PAY-2026-000023',
    ])
    expect(Object.keys(blocked.body.meta.transfers[0]).sort()).toEqual([
      'currency',
      'entity_name',
      'payment_id',
      'reference',
      'submitted_at',
      'total',
    ])
    seedMockSession(OFFICER)
    const outcome = await call('/finance/bank-transfers/bulk-approve', {
      method: 'POST',
      body: { payment_ids: blocked.body.meta.transfers.map((item) => item.payment_id) },
    })
    expect(outcome.body.data.decided).toHaveLength(3)
    seedMockSession(ADMIN)
    const archived = await call(`/settings/payment/bank-accounts/${EXAMPLE_ACCOUNT_ID}`, {
      method: 'DELETE',
    })
    expect(archived.status).toBe(204)
    const listed = await call('/settings/payment/bank-accounts')
    expect(listed.body.data.map((account) => account.id)).not.toContain(EXAMPLE_ACCOUNT_ID)
  })

  it('keeps the last active account while bank transfer is enabled', async () => {
    seedMockSession(ADMIN)
    financeState().transfers = []
    const refused = await call(`/settings/payment/bank-accounts/${EXAMPLE_ACCOUNT_ID}/deactivate`, {
      method: 'POST',
      body: {},
    })
    expect([refused.status, refused.body.code]).toEqual([409, 'bank_account.last_active_account'])
  })

  it('refuses payee changes and reorders the listed accounts', async () => {
    seedMockSession(ADMIN)
    const created = await call('/settings/payment/bank-accounts', {
      method: 'POST',
      body: newAccount(),
    })
    const payee = await call(`/settings/payment/bank-accounts/${created.body.data.id}`, {
      method: 'PATCH',
      body: { iban: VALID_IBAN, payer_note: null },
    })
    expect(payee.body.code).toBe('bank_account.payee_fields_immutable')
    const order = await call('/settings/payment/bank-accounts/order', {
      method: 'PUT',
      body: { ordered_ids: [created.body.data.id, EXAMPLE_ACCOUNT_ID] },
    })
    expect(order.body.data.map((account) => account.id)).toEqual([
      created.body.data.id,
      EXAMPLE_ACCOUNT_ID,
    ])
  })
})

describe('entity bank-transfer checkout mock contract', () => {
  it('lists the available channels, the selectable accounts and the unified number', async () => {
    seedMockSession(ENTITY)
    const { body } = await call('/payments/checkout-options')
    expect(body.data.channels).toEqual(['electronic', 'bank_transfer'])
    expect(body.data.bank_accounts.map((account) => account.iban)).toEqual([
      'SA0380000000608010167519',
    ])
    expect(body.data.transfer_note.unified_number).toBe(findEntity(5001).unifiedNumber)
  })

  it('opens a transfer under review with its scanned receipt', async () => {
    const form = transferForm({
      purpose: 'accredited',
      channel: 'bank_transfer',
      bankAccountId: EXAMPLE_ACCOUNT_ID,
      proof: pdf(),
    })
    const order = validatedTransfer(form)
    const proof = await acceptProof(order.proof)
    const payment = openTransfer({
      user: ENTITY,
      entity: findEntity(5001),
      purpose: 'accredited',
      quote: {
        priceBeforeVat: '1000.00',
        vatRate: '15.00',
        vatAmount: '150.00',
        total: '1150.00',
        currency: 'SAR',
      },
      key: crypto.randomUUID(),
      account: financeState().bankAccounts[0],
      proof,
    })
    expect([payment.channel, payment.status, payment.expiresAt]).toEqual([
      'bank_transfer',
      'under-review',
      null,
    ])
    seedMockSession(ENTITY)
    const own = await call(`/payments/${payment.id}`)
    expect(own.body.data.bank_transfer).toMatchObject({
      bank_account: { id: EXAMPLE_ACCOUNT_ID, iban: 'SA0380000000608010167519' },
      proof: { name: 'bank-transfer-receipt.pdf', mime_type: 'application/pdf' },
      rejection_reason: null,
    })
    const card = await call('/payments', {
      method: 'POST',
      body: { purpose: 'accredited' },
      headers: { 'idempotency-key': crypto.randomUUID() },
    })
    expect([card.status, card.body.code]).toEqual([409, 'payment.open_order_exists'])
    expect(card.body.meta).toMatchObject({ payment_id: payment.id, channel: 'bank_transfer' })
  })

  it('refuses a disguised or infected receipt and a missing account', async () => {
    const disguised = await rejectionOf(acceptProof(pdf('PK not a pdf')))
    expect(disguised.code).toBe('file.content_mismatch')
    const infected = await rejectionOf(
      acceptProof(pdf('%PDF-1.4 EICAR-STANDARD-ANTIVIRUS-TEST-FILE')),
    )
    expect(infected.code).toBe('file.infected')
    const missing = await rejectionOf(
      Promise.resolve().then(() =>
        validatedTransfer(transferForm({ purpose: 'bag', channel: 'bank_transfer' })),
      ),
    )
    expect(Object.keys(missing.fields).sort()).toEqual(['bankAccountId', 'proof'])
  })

  it('answers a card request with the open transfer of the same service', async () => {
    seedMockSession({ id: 6002, roles: ['entity'], entityId: 6002 })
    const response = await call('/payments', {
      method: 'POST',
      body: { purpose: 'bag' },
      headers: { 'idempotency-key': crypto.randomUUID() },
    })
    expect([response.status, response.body.code]).toEqual([409, 'payment.open_order_exists'])
    expect(response.body.meta).toEqual({
      payment_id: BANK_TRANSFER_IDS.underReviewBag,
      channel: 'bank_transfer',
      status: 'under_review',
    })
  })
})

describe('finance bank-transfer review mock contract', () => {
  it('lists transfers under review oldest first with counts and flags', async () => {
    seedMockSession(OFFICER)
    const { body } = await call('/finance/bank-transfers')
    expect(body.data.map((item) => item.reference)).toEqual([
      'PAY-2026-000021',
      'PAY-2026-000022',
      'PAY-2026-000023',
    ])
    expect(body.meta.counts).toEqual({ under_review: 3, approved_today: 0, rejected_today: 0 })
    expect(body.data[1].has_duplicate_proof).toBe(true)
    expect(body.data[1].duplicate_proofs).toBeNull()
    const detail = await call(`/finance/bank-transfers/${BANK_TRANSFER_IDS.underReviewBag}`)
    expect(detail.body.data.duplicate_proofs.map((item) => item.reference)).toEqual([
      'PAY-2026-000024',
    ])
    const rejected = await call('/finance/bank-transfers?filter[status]=rejected')
    expect(rejected.body.data.map((item) => item.rejection_reason)).toEqual(['not_received'])
  })

  it('approves once, refuses a second decision and lets only reviewers decide', async () => {
    seedMockSession(ADMIN)
    const forbidden = await call(
      `/finance/bank-transfers/${BANK_TRANSFER_IDS.underReviewAccredited}/approve`,
      { method: 'POST', body: {} },
    )
    expect(forbidden.status).toBe(403)
    seedMockSession(OFFICER)
    const approved = await call(
      `/finance/bank-transfers/${BANK_TRANSFER_IDS.underReviewAccredited}/approve`,
      { method: 'POST', body: {} },
    )
    expect(approved.body.data).toMatchObject({ status: 'paid', reviewed_by: { id: 22 } })
    expect(approved.body.data.receipt.is_test).toBe(true)
    const again = await call(
      `/finance/bank-transfers/${BANK_TRANSFER_IDS.underReviewAccredited}/reject`,
      { method: 'POST', body: { rejection_reason: 'unreadable' } },
    )
    expect([again.status, again.body.code]).toEqual([409, 'state.transition_illegal'])
  })

  it('requires a note for another reason and records the rejection', async () => {
    seedMockSession(OFFICER)
    const path = `/finance/bank-transfers/${BANK_TRANSFER_IDS.underReviewBag}/reject`
    const noNote = await call(path, { method: 'POST', body: { rejection_reason: 'other' } })
    expect(Object.keys(noNote.body.errors)).toEqual(['note'])
    const rejected = await call(path, {
      method: 'POST',
      body: { rejection_reason: 'other', note: 'The receipt shows a different beneficiary.' },
    })
    expect(rejected.body.data).toMatchObject({
      status: 'rejected',
      rejection_reason: 'other',
      review_note: 'The receipt shows a different beneficiary.',
    })
    expect(findPayment(BANK_TRANSFER_IDS.underReviewBag).rejectedAt).not.toBeNull()
  })

  it('decides in bulk and reports the skipped transfers', async () => {
    seedMockSession(SUPER_ADMIN)
    const ids = [BANK_TRANSFER_IDS.underReviewLatest, BANK_TRANSFER_IDS.paidBag]
    const noReason = await call('/finance/bank-transfers/bulk-reject', {
      method: 'POST',
      body: { payment_ids: ids, rejection_reason: 'amount_mismatch' },
    })
    expect(noReason.body.code).toBe('reason_required')
    const outcome = await call('/finance/bank-transfers/bulk-reject', {
      method: 'POST',
      body: { payment_ids: ids, rejection_reason: 'amount_mismatch', reason: REASON },
    })
    expect(outcome.body.data).toEqual({
      decided: [BANK_TRANSFER_IDS.underReviewLatest],
      skipped: [{ id: BANK_TRANSFER_IDS.paidBag, code: 'state.transition_illegal' }],
    })
  })

  it('counts the approvals and rejections decided on the current Riyadh day', async () => {
    vi.useFakeTimers({ toFake: ['Date'] })
    seedMockSession(OFFICER)
    const decide = (id, action, body = {}) =>
      call(`/finance/bank-transfers/${id}/${action}`, { method: 'POST', body })
    vi.setSystemTime(new Date('2026-11-05T20:59:00Z'))
    await decide(BANK_TRANSFER_IDS.underReviewAccredited, 'approve')
    vi.setSystemTime(new Date('2026-11-05T21:00:00Z'))
    await decide(BANK_TRANSFER_IDS.underReviewBag, 'approve')
    await decide(BANK_TRANSFER_IDS.underReviewLatest, 'reject', { rejection_reason: 'unreadable' })
    const { body } = await call('/finance/bank-transfers?filter[status]=all&filter[mode]=live')
    expect(body.meta.counts).toEqual({ under_review: 0, approved_today: 1, rejected_today: 1 })
    vi.setSystemTime(new Date('2026-11-06T21:00:00Z'))
    const nextDay = await call('/finance/bank-transfers')
    expect(nextDay.body.meta.counts).toEqual({
      under_review: 0,
      approved_today: 0,
      rejected_today: 0,
    })
  })

  it('lists the destination accounts for Finance with the last 4 digits and the state only', async () => {
    seedMockSession(OFFICER)
    const first = await call('/finance/bank-transfers/accounts')
    expect(first.status).toBe(200)
    expect(first.body.data).toEqual([
      {
        id: EXAMPLE_ACCOUNT_ID,
        bank_name: { ar: 'بنك المثال (مثال)', en: 'Example Bank (example)' },
        iban_last4: '7519',
        state: 'active',
      },
    ])
    expect(first.body.meta.pagination).toEqual({ page: 1, per_page: 50, total: 1, last_page: 1 })
    seedMockSession(ADMIN)
    const created = await call('/settings/payment/bank-accounts', {
      method: 'POST',
      body: newAccount(),
    })
    await call(`/settings/payment/bank-accounts/${created.body.data.id}/deactivate`, {
      method: 'POST',
      body: {},
    })
    financeState().bankAccounts.push({
      ...financeState().bankAccounts[0],
      id: crypto.randomUUID(),
      iban: 'SA0400000000000000000011',
      isActive: false,
      archivedAt: '2026-11-01T08:00:00Z',
    })
    const listed = await call('/finance/bank-transfers/accounts?per_page=100')
    expect(listed.body.data.map((account) => [account.iban_last4, account.state])).toEqual([
      ['7519', 'active'],
      ['7890', 'inactive'],
    ])
    expect(JSON.stringify(listed.body)).not.toContain('SA0380000000608010167519')
  })

  it('keeps archived accounts used by a transfer in the Finance account list', async () => {
    seedMockSession(ADMIN)
    await call('/settings/payment/bank-accounts', { method: 'POST', body: newAccount() })
    seedMockSession(OFFICER)
    const pending = await call('/finance/bank-transfers')
    await call('/finance/bank-transfers/bulk-approve', {
      method: 'POST',
      body: { payment_ids: pending.body.data.map((item) => item.id) },
    })
    seedMockSession(ADMIN)
    await call(`/settings/payment/bank-accounts/${EXAMPLE_ACCOUNT_ID}`, { method: 'DELETE' })
    const listed = await call('/finance/bank-transfers/accounts')
    expect(listed.body.data.map((account) => [account.iban_last4, account.state])).toEqual([
      ['7890', 'active'],
      ['7519', 'archived'],
    ])
  })

  it('refuses the account list to users without finance access and validates the page size', async () => {
    seedMockSession(ENTITY)
    expect((await call('/finance/bank-transfers/accounts')).status).toBe(403)
    seedMockSession(OFFICER)
    const tooMany = await call('/finance/bank-transfers/accounts?per_page=101')
    expect([tooMany.status, tooMany.body.code]).toEqual([422, 'validation_failed'])
    expect(Object.keys(tooMany.body.errors)).toEqual(['per_page'])
  })

  it('shows the reviewer action item with the count of transfers under review', async () => {
    seedMockSession(OFFICER)
    const { body } = await call('/dashboard/summary')
    expect(
      body.data.action_center.find((item) => item.key === 'review_bank_transfers'),
    ).toMatchObject({
      count: 3,
      link: '/finance/transfers',
    })
  })
})
