import { randomUUID } from 'node:crypto'
import { test, expect } from '@playwright/test'
import { assertMailLogsConfigured } from './support/mailbox'
import {
  FOOTER_LEGAL_PROBES,
  GUEST_SESSION_PROBE,
  PAGE_NOT_PUBLISHED,
  SOURCES,
  watchApi,
} from './support/apiWatch'
import { registerEntity } from './support/entities'
import { api as callApi } from './support/api'
import { squarePng } from './support/images'
import { chooseNamed } from '../support/dropdown'

const RUN = Date.now().toString(36)
const LICENSE = String(Date.now()).slice(-9)
const ENTITY = {
  email: `areas.${RUN}@albayan-association.example.sa`,
  password: `Bayan-${randomUUID()}`,
  nameAr: 'جمعية البيان للتنمية المجتمعية',
  nameEn: 'Al-Bayan Community Development Association',
  person: 'ريم بنت عبدالله السبيعي',
}
const ALLOWED = [GUEST_SESSION_PROBE, PAGE_NOT_PUBLISHED, ...FOOTER_LEGAL_PROBES]
const UNKNOWN_CODE = 'AAAA-BBBB-CCCC-DDDD-EEEE-FFFF-GGGG'
const LEGAL_KINDS = ['terms', 'privacy']
const BLOCK_TYPES = ['heading', 'paragraph', 'list', 'table']

const session = {}

test.describe.configure({ mode: 'serial' })

async function settled(page) {
  await session.api.idle()
  await expect(page.locator('[aria-busy="true"], .skeleton')).toHaveCount(0)
}

function shot(page, name) {
  return page.screenshot({ path: `test-results/integration-37b-${name}.png`, fullPage: true })
}

function backend(path) {
  expect(session.api.sourcesOf(path)).toEqual([SOURCES.BACKEND])
}

test.beforeAll(async ({ browser }) => {
  assertMailLogsConfigured()
  const context = await browser.newContext({ locale: 'ar-SA' })
  session.context = context
  session.page = await context.newPage()
  session.api = watchApi(session.page)
})

test.afterAll(async () => {
  await session.context.close()
})

test('the profile tabs save the registry details and the logo on the real contract', async () => {
  const { page, api } = session
  await registerEntity(page, ENTITY)
  await settled(page)
  await expect(page.locator('[role="tab"]')).toHaveCount(3)
  const before = await page.locator('.profile-completeness__value').textContent()
  await page.locator('input[name="licenseNumber"]').fill(LICENSE)
  await page.locator('input[name="foundedOn"]').fill('2012-03-01')
  await page.locator('.portal-area__actions .btn--primary').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  await settled(page)
  await expect(page.locator('.profile-completeness__value')).not.toHaveText(before ?? '')
  await expect(page.locator('input[name="foundingYear"]')).toHaveValue('2012')
  await page
    .locator('.entity-logo__input')
    .setInputFiles({ name: 'albayan-logo.png', mimeType: 'image/png', buffer: squarePng(240) })
  await expect(page.locator('.entity-logo__image')).toBeVisible()
  await settled(page)
  await shot(page, 'profile')
  await page.locator('[role="tab"]').nth(1).click()
  await page.locator('input[name="websiteUrl"]').fill('https://www.albayan.example.sa')
  await page.locator('.portal-area__actions .btn--primary').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  backend(/^\/api\/v1\/entities\/me$/)
  backend(/^\/api\/v1\/entities\/me\/logo$/)
  expect(api.unexpected(ALLOWED)).toEqual([])
})

test('a signed-in message opens its thread and the follow-up reaches the backend', async () => {
  const { page, api } = session
  await page.goto('/messages')
  await settled(page)
  await expect(page.locator('[role="tab"]')).toHaveCount(2)
  await page.locator('.portal-area__actions .btn--primary').click()
  const dialog = page.locator('.modal__panel')
  await chooseNamed(dialog, 'enquiryType', 'privacy_request')
  await dialog
    .locator('textarea[name="message"]')
    .fill('نرجو تزويدنا بنسخة من البيانات المحفوظة في حساب الجهة.')
  await dialog.locator('.checkbox').first().click()
  await dialog.locator('.modal__footer .btn--primary').click()
  await expect(page).toHaveURL(/\/messages\/[0-9a-f-]{36}$/)
  await expect(page.locator('.message-bubble')).toHaveCount(1)
  await page
    .locator('.message-thread__composer textarea')
    .fill('ونرجو إرسال النسخة إلى البريد الرسمي للجهة.')
  await page.locator('.message-thread__composer .btn--primary').click()
  await expect(page.locator('.message-bubble')).toHaveCount(2)
  await settled(page)
  await shot(page, 'messages')
  backend(/^\/api\/v1\/me\/contact-messages$/)
  backend(/^\/api\/v1\/me\/contact-messages\/[0-9a-f-]{36}\/replies$/)
  expect(api.unexpected(ALLOWED)).toEqual([])
})

test('the assessments, payments and certificates hubs read the tab counts from the backend', async () => {
  const { page, api } = session
  await page.goto('/assessment/history')
  await settled(page)
  await expect(page.locator('[role="tab"] .area-tabs__count')).toHaveCount(3)
  await expect(page.locator('.assessment-start__card').first()).toBeVisible()
  await shot(page, 'assessments')
  await page.goto('/payments')
  await expect(page.locator('[role="tab"] .area-tabs__count')).toHaveCount(4)
  await expect(page.locator('.payment-stats__tile')).toHaveCount(4)
  await expect(page.locator('.payments-hub__records .empty-state')).toBeVisible()
  await settled(page)
  await shot(page, 'payments')
  await page.goto('/certificates')
  await settled(page)
  await expect(page.locator('[role="tab"] .area-tabs__count')).toHaveCount(3)
  await expect(page.locator('.verify-guide')).toBeVisible()
  await expect(page.locator('.demo-notice')).toHaveCount(0)
  await shot(page, 'certificates')
  backend(/^\/api\/v1\/assessments$/)
  backend(/^\/api\/v1\/payments\/mine$/)
  backend(/^\/api\/v1\/certificates$/)
  expect(api.unexpected(ALLOWED)).toEqual([])
})

test('the verification and legal pages stay reachable while the public site waits for its homepage', async () => {
  const { page, api } = session
  const site = await callApi(page, 'GET', '/content/status')
  expect(site.body.data.maintenance).toBe(true)
  const verification = await callApi(page, 'GET', `/verify/${UNKNOWN_CODE}`)
  expect(verification.status).toBe(200)
  expect(verification.body.data.result).toBe('not_found')
  for (const kind of LEGAL_KINDS) {
    const { status, body } = await callApi(page, 'GET', `/content/legal/${kind}`)
    expect(status).toBe(200)
    expect(body.data.kind).toBe(kind)
    const types = [...body.data.body.ar, ...body.data.body.en].map((block) => block.type)
    expect(types.filter((type) => !BLOCK_TYPES.includes(type))).toEqual([])
  }
  await page.goto('/assessment/history')
  await settled(page)
  await expect(page.locator('.portal-footer a[href="/privacy"]')).toHaveCount(1)
  await page.goto(`/verify/${UNKNOWN_CODE}`)
  await expect(page.locator('.verification-result')).toBeVisible()
  await expect(page).toHaveURL(new RegExp(`/verify/${UNKNOWN_CODE}$`))
  await page.goto('/privacy')
  await expect(page.locator('.legal-page')).toBeVisible()
  await expect(page).toHaveURL(/\/privacy$/)
  await page.goto('/kpis')
  await expect(page).toHaveURL(/\/maintenance$/)
  await settled(page)
  await shot(page, 'legal-maintenance')
  backend(/^\/api\/v1\/verify\//)
  backend(/^\/api\/v1\/content\/legal\/(terms|privacy)$/)
  expect(api.unexpected(ALLOWED)).toEqual([])
})
