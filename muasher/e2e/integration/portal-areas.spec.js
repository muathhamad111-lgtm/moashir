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
import { paced } from './support/pacing'

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
const STATUS_CARD = '[data-testid="path-status-card"]'
const PRIMARY_ACTION = '[data-testid="path-primary-action"]'

const session = {}

test.describe.configure({ mode: 'serial' })

async function settled(page) {
  await session.api.idle()
  await expect(page.locator('[aria-busy="true"], .skeleton')).toHaveCount(0)
}

async function visit(page, path) {
  await paced('pageLoad')
  await page.goto(path)
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

test('the profile sections save the registry details and the logo on the real contract', async () => {
  const { page, api } = session
  await registerEntity(page, ENTITY)
  await settled(page)
  const percent = page.locator('.profile-completeness__percent')
  await expect(percent).toHaveText('73%')
  const identity = page.locator('[data-section="identity"]')
  await identity.locator('[data-field="licenseNumber"] .profile-missing__add').click()
  await identity.locator('input[name="licenseNumber"]').fill(LICENSE)
  await identity.locator('input[name="foundedOn"]').fill('2012-03-01')
  await identity.locator('button[type="submit"]').click()
  await expect(identity.locator('.profile-section__status-text--success')).toBeVisible()
  await settled(page)
  await expect(percent).toHaveText('86%')
  await page.getByTestId('profile-change-logo').click()
  await page
    .getByTestId('logo-dialog')
    .locator('.logo-dialog__input')
    .setInputFiles({ name: 'albayan-logo.png', mimeType: 'image/png', buffer: squarePng(240) })
  await expect(page.locator('.profile-header__logo-image')).toBeVisible()
  await page.keyboard.press('Escape')
  await settled(page)
  await shot(page, 'profile')
  const contact = page.locator('[data-section="contact"]')
  await contact.locator('.profile-section__edit').click()
  await contact.locator('input[name="websiteUrl"]').fill('https://www.albayan.example.sa')
  await contact.locator('button[type="submit"]').click()
  await expect(contact.locator('.profile-section__status-text--success')).toBeVisible()
  backend(/^\/api\/v1\/entities\/me$/)
  backend(/^\/api\/v1\/entities\/me\/profile\/identity$/)
  backend(/^\/api\/v1\/entities\/me\/profile\/contact$/)
  backend(/^\/api\/v1\/entities\/me\/logo$/)
  expect(api.unexpected(ALLOWED)).toEqual([])
})

test('a signed-in message opens its thread and the follow-up reaches the backend', async () => {
  const { page, api } = session
  await visit(page, '/messages')
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

function sideNavLink(page, path) {
  return page.locator(`[data-testid="portal-side-nav"] a[href="${path}"]`)
}

async function openFromSideNav(page, path) {
  await sideNavLink(page, path).click()
  await expect(page).toHaveURL(new RegExp(`${path}$`))
  await settled(page)
}

async function openPathPage(page, path) {
  await openFromSideNav(page, `/paths/${path}`)
  await expect(page.locator(`[data-testid="path-page-${path}"] ${STATUS_CARD}`)).toBeVisible()
  await expect(page.locator('[data-testid="path-requests"]')).toBeVisible()
  await expect(page.locator('[data-testid="path-requests"] .error-state')).toHaveCount(0)
}

test('the path pages, the payments and certificates hubs read their lists from the backend', async () => {
  const { page, api } = session
  await visit(page, '/assessment/history')
  await expect(page).toHaveURL(/\/dashboard$/)
  await expect(page.locator('[data-testid="path-tiles"]')).toBeVisible()
  await settled(page)
  await openPathPage(page, 'self')
  await expect(page.locator(STATUS_CARD)).toHaveAttribute('data-state', 'start')
  await expect(page.locator(PRIMARY_ACTION)).toBeVisible()
  await expect(page.locator('[data-testid="path-requests"] .empty-state')).toBeVisible()
  await shot(page, 'path-self')
  await openPathPage(page, 'accredited')
  await expect(page.locator(PRIMARY_ACTION)).toHaveCount(0)
  await expect(
    page.locator('[data-testid="path-header-accredited"] .path-price__muted'),
  ).toBeVisible()
  await shot(page, 'path-accredited')
  await openPathPage(page, 'award')
  await expect(page.locator(STATUS_CARD)).toHaveAttribute('data-state', 'none')
  await shot(page, 'path-award')
  await openFromSideNav(page, '/payments')
  await expect(page.locator('[role="tab"] .area-tabs__count')).toHaveCount(4)
  await expect(page.locator('.payment-stats__tile')).toHaveCount(4)
  await expect(page.locator('.payments-hub__records .empty-state')).toBeVisible()
  await settled(page)
  await shot(page, 'payments')
  await openFromSideNav(page, '/certificates')
  await expect(page.locator('[role="tab"] .area-tabs__count')).toHaveCount(3)
  await expect(page.locator('.verify-guide')).toBeVisible()
  await shot(page, 'certificates')
  backend(/^\/api\/v1\/assessments$/)
  backend(/^\/api\/v1\/dashboard\/summary$/)
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
  await visit(page, '/paths/self')
  await settled(page)
  await expect(page.locator('.portal-footer a[href="/privacy"]')).toHaveCount(1)
  await visit(page, `/verify/${UNKNOWN_CODE}`)
  await expect(page.locator('.verification-result')).toBeVisible()
  await expect(page).toHaveURL(new RegExp(`/verify/${UNKNOWN_CODE}$`))
  await visit(page, '/privacy')
  await expect(page.locator('.legal-page')).toBeVisible()
  await expect(page).toHaveURL(/\/privacy$/)
  await visit(page, '/kpis')
  await expect(page).toHaveURL(/\/maintenance$/)
  await settled(page)
  await shot(page, 'legal-maintenance')
  backend(/^\/api\/v1\/verify\//)
  backend(/^\/api\/v1\/content\/legal\/(terms|privacy)$/)
  expect(api.unexpected(ALLOWED)).toEqual([])
})
