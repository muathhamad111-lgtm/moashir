import { randomInt, randomUUID } from 'node:crypto'
import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { closeSuccess, decisionAction } from './support/dialogs'
import { assertMailLogsConfigured } from './support/mailbox'
import { STAFF, signInStaff } from './support/accounts'
import { FOOTER_LEGAL_PROBES, GUEST_SESSION_PROBE, SOURCES, watchApi } from './support/apiWatch'
import { registerEntity } from './support/entities'

const RUN = Date.now().toString(36)
const ENTITY = {
  email: `finance.${RUN}@alwafa-association.example.sa`,
  password: `Wafa-${randomUUID()}`,
  nameAr: 'جمعية الوفاء الأهلية',
  nameEn: 'Al-Wafa Community Association',
  person: 'نورة بنت سعد الحربي',
}
const ACCREDITED_PRICE = '1500'
const IBAN_LENGTH = 24
const BANK_CODE = '00'
const ACCOUNT_DIGITS = 18
const CHECK_MOD = 97n
const CHECK_BASE = 98n
const REJECTION_NOTE = 'The amount has not reached the fees account yet.'

const EXPECTED_PROBES = [GUEST_SESSION_PROBE, ...FOOTER_LEGAL_PROBES]

const sessions = {}
const transfers = { second: null }
const account = { iban: null }

function fictionalIban() {
  const account = Array.from({ length: ACCOUNT_DIGITS }, () => randomInt(10)).join('')
  const body = `${BANK_CODE}${account}`
  const numeric = `${body}281000`
  const check = CHECK_BASE - (BigInt(numeric) % CHECK_MOD)
  const iban = `SA${String(check).padStart(2, '0')}${body}`
  expect(iban).toHaveLength(IBAN_LENGTH)
  return iban
}

function receipt() {
  return {
    name: 'bank-transfer-receipt.pdf',
    mimeType: 'application/pdf',
    buffer: Buffer.from('%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n'),
  }
}

async function openSession(browser, key) {
  const context = await browser.newContext({ locale: 'ar-SA' })
  const page = await context.newPage()
  sessions[key] = { context, page, api: watchApi(page) }
}

function shot(page, name) {
  return page.screenshot({
    path: `test-results/integration-bank-transfer-${name}.png`,
    fullPage: true,
  })
}

async function sendTransfer(page) {
  await page.goto('/assessment/accredited/pay')
  const choice = page.locator('.method-option[data-value="bank_transfer"]')
  await expect(choice.or(page.locator('.transfer-panel')).first()).toBeVisible()
  if (await choice.isVisible()) {
    await choice.click()
    await page.locator('.checkout__start .btn--primary').click()
  }
  await page.locator('input[type="file"][name="proof"]').setInputFiles(receipt())
  const created = page.waitForRequest(
    (request) => request.method() === 'POST' && request.url().endsWith('/api/v1/payments'),
  )
  await page.locator('.transfer-panel__submit .btn--primary').click()
  const request = await created
  expect(request.headers()['idempotency-key']).toMatch(/^[0-9a-f-]{36}$/)
  expect(request.headers()['content-type']).toMatch(/^multipart\/form-data/)
  await expect(page).toHaveURL(/\/payments\/[0-9a-f-]{36}\/complete$/)
  await expect(page.locator('.transfer-status--info')).toBeVisible()
  return page.url().split('/').at(-2)
}

async function decide(page, paymentId, action) {
  await page.goto(`/finance/transfers/${paymentId}`)
  await expect(page.locator('.transfer-review__title')).toBeVisible()
  await expect(page.locator('.proof-viewer__name')).toContainText('bank-transfer-receipt.pdf')
  await page
    .locator(`.transfer-review__decisions .btn--${action === 'approve' ? 'primary' : 'secondary'}`)
    .click()
  if (action === 'reject') {
    const dialog = page.locator('.modal__panel').last()
    await dialog.locator('label.radio:has(input[value="not_received"])').click()
    await dialog.locator('textarea[name="note"]').fill(REJECTION_NOTE)
    await dialog.locator('button[type="submit"]').click()
  } else {
    await decisionAction(page).click()
  }
  await closeSuccess(page)
  await expect(page.locator('.transfer-decision-line')).toBeVisible()
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async ({ browser, request }) => {
  assertMailLogsConfigured()
  const health = await request.get('/api/v1/health', { headers: { Accept: 'application/json' } })
  expect(health.status()).toBe(200)
  await openSession(browser, 'admin')
  await openSession(browser, 'finance')
  await openSession(browser, 'entity')
})

test.afterAll(async () => {
  await Promise.all(Object.values(sessions).map(({ context }) => context.close()))
})

test('the System Administrator adds a bank account and turns bank transfer on', async () => {
  const { page, api } = sessions.admin
  await signInStaff(page, STAFF.systemAdmin)
  await page.goto('/admin/settings/finance')
  const price = page.locator('input[name="accreditedPrice"]')
  await expect(price).toBeEditable()
  await price.fill(ACCREDITED_PRICE)
  await page.locator('.settings-section .btn--primary').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()

  await page.goto('/admin/settings/payment')
  await expect(page.locator('.channels-card .method-option').first()).toBeVisible()
  await page.locator('.bank-accounts-card .btn--primary').first().click()
  const form = page.locator('.modal__panel').last()
  await form.locator('[name="bankName.ar"]').fill('مصرف العيّنة')
  await form.locator('[name="bankName.en"]').fill('Sample Bank')
  await form.locator('[name="accountHolderName.ar"]').fill('منصة مؤشر — حساب الرسوم')
  await form.locator('[name="accountHolderName.en"]').fill('Moashir Platform — fees account')
  account.iban = fictionalIban()
  await form.locator('[name="iban"]').fill(account.iban)
  const created = page.waitForResponse(
    (response) =>
      response.request().method() === 'POST' &&
      response.url().endsWith('/settings/payment/bank-accounts'),
  )
  await form.locator('button[type="submit"]').click()
  expect((await created).status()).toBe(201)

  await page.locator('.channels-card .method-option[data-value="bank_transfer"]').click()
  const saved = page.waitForResponse(
    (response) =>
      response.request().method() === 'PUT' &&
      response.url().endsWith('/settings/payment/channels'),
  )
  await page.locator('.channels-card__actions .btn').click()
  const body = await (await saved).json()
  expect(body.data.channels).toContain('bank_transfer')
  expect(body.data.reviewers_count).toBeGreaterThan(0)
  await shot(page, 'settings')
  expect(api.unexpected(EXPECTED_PROBES)).toEqual([])
})

test('the entity pays by transfer, Finance rejects it and the entity sends a new request', async () => {
  test.setTimeout(240000)
  const entity = sessions.entity
  const finance = sessions.finance
  await registerEntity(entity.page, ENTITY)
  const first = await sendTransfer(entity.page)
  await shot(entity.page, 'under-review')

  await signInStaff(finance.page, STAFF.financeOfficer)
  await finance.page.goto('/finance/transfers')
  await expect(finance.page.locator('.transfer-queue__table')).toContainText(ENTITY.nameAr)
  await decide(finance.page, first, 'reject')

  await entity.page.goto(`/payments/${first}/complete`)
  await expect(entity.page.locator('.transfer-status--danger')).toBeVisible()
  await expect(entity.page.locator('.transfer-status__note')).toContainText(REJECTION_NOTE)
  await shot(entity.page, 'rejected')
  await entity.page.locator('.transfer-status a.btn--primary').click()
  transfers.second = await sendTransfer(entity.page)
  expect(transfers.second).not.toBe(first)
  expect(entity.api.unexpected(EXPECTED_PROBES)).toEqual([])
  expect(finance.api.unexpected(EXPECTED_PROBES)).toEqual([])
})

test('the account cannot be deleted while the transfer waits, and the list names it', async () => {
  const { page } = sessions.admin
  await page.goto('/admin/settings/payment')
  const sample = page.locator('.bank-accounts__table tbody tr', { hasText: 'مصرف العيّنة' })
  await sample.locator('.dropdown-menu > button').click()
  await page.getByRole('menuitem', { name: 'حذف الحساب' }).click()
  const deleted = page.waitForResponse((response) => response.request().method() === 'DELETE')
  await page.locator('.modal__panel').last().locator('.modal__footer .btn--danger').click()
  const answer = await deleted
  expect(answer.status()).toBe(409)
  const body = await answer.json()
  expect(body.code).toBe('bank_account.pending_transfers')
  expect(body.meta.transfers.map((item) => item.payment_id)).toContain(transfers.second)
  await expect(page.locator('.pending-transfers__table tbody tr').first()).toBeVisible()
  await expect(page.locator('.pending-transfers input[type="checkbox"]')).toHaveCount(0)
  await shot(page, 'blocked')
})

test('Finance approves the new request and the entity gets its payment receipt', async () => {
  const entity = sessions.entity
  const finance = sessions.finance
  await decide(finance.page, transfers.second, 'approve')
  await shot(finance.page, 'approved')
  await entity.page.goto(`/payments/${transfers.second}`)
  await expect(entity.page.locator('.receipt-card')).toBeVisible()
  await expect(entity.page.locator('.transfer-record')).toBeVisible()
  expect(finance.api.unexpected(EXPECTED_PROBES)).toEqual([])
})

test('the Finance queue counts the decisions of the day and filters by the receiving account', async () => {
  const { page, api } = sessions.finance
  const listed = page.waitForResponse((response) =>
    response.url().endsWith('/api/v1/finance/bank-transfers/accounts?per_page=100'),
  )
  await page.goto('/finance/transfers')
  const accounts = await (await listed).json()
  expect(accounts.data).toEqual([
    {
      id: expect.any(String),
      bank_name: { ar: 'مصرف العيّنة', en: 'Sample Bank' },
      iban_last4: account.iban.slice(-4),
      state: 'active',
    },
  ])
  await expect(page.locator('.stats-card__value')).toHaveText(['0', '1', '1'])
  await page.locator('.transfer-filters [role="combobox"]').first().click()
  await page
    .locator('.dropdown-popup')
    .getByRole('option', { name: `مصرف العيّنة · ينتهي بـ ${account.iban.slice(-4)}` })
    .click()
  await page.locator('[role="tab"]').last().click()
  await expect(page.locator('.transfer-queue__table tbody tr')).toHaveCount(2)
  await shot(page, 'queue-today')
  expect(api.sourcesOf(/^\/api\/v1\/finance\/bank-transfers(\/accounts)?$/)).toEqual([
    SOURCES.BACKEND,
  ])
  expect(api.unexpected(EXPECTED_PROBES)).toEqual([])
})
