import { randomUUID } from 'node:crypto'
import { test, expect } from '@playwright/test'
import { assertMailLogsConfigured } from './support/mailbox'
import { STAFF, signInStaff } from './support/accounts'
import { writeFileSync } from 'node:fs'
import { GUEST_SESSION_PROBE, PAGE_NOT_PUBLISHED, SOURCES, watchApi } from './support/apiWatch'
import { registerEntity } from './support/entities'

const RUN = Date.now().toString(36)
const ENTITY = {
  email: `office.${RUN}@alnoor-association.example.sa`,
  password: `Noor-${randomUUID()}`,
  nameAr: 'جمعية النور للتنمية الأهلية',
  nameEn: 'Al-Noor Community Development Association',
  person: 'ريم بنت عبدالعزيز السبيعي',
}
const ACCREDITED_PRICE = '1500'
const PAYMENT_UNAVAILABLE = { status: 503, path: /^\/api\/v1\/payments$/ }

const sessions = {}

const ADMIN_SCREENS = [
  '/dashboard',
  '/admin/settings',
  '/admin/entities',
  '/admin/entities/deleted',
  '/admin/entities/email-changes',
  '/admin/privacy-requests',
  '/admin/settings/cities',
  '/admin/permissions',
  '/admin/settings/assessment',
  '/admin/settings/sms',
  '/admin/settings/email',
  '/admin/settings/ai',
  '/admin/settings/abandoned-carts',
  '/admin/award/filtering',
  '/admin/award/cycles/new',
  '/admin/settings/users',
  '/admin/settings/homepage',
  '/admin/kpis',
  '/admin/branding',
  '/admin/award/committee/manage',
  '/admin/award/secretary',
  '/admin/award/results',
  '/admin/requests',
  '/finance',
  '/finance/transactions',
  '/finance/refunds',
  '/finance/payment-events',
  '/account',
]
const ENTITY_SCREENS = [
  '/assessment/history',
  '/payments',
  '/assessment/advanced',
  '/assessment/accredited',
  '/certificates',
  '/bag',
  '/account',
  '/entity/profile',
]

async function sweep({ page, api }, role, paths) {
  const broken = []
  for (const path of paths) {
    await page.goto(path)
    await settled(page, api)
    await shot(page, `${role}${path.replaceAll('/', '-')}`)
    if (await page.locator('.error-state').count()) broken.push(path)
  }
  return broken
}

async function settled(page, api) {
  await api.idle()
  await expect(page.locator('[aria-busy="true"], .skeleton')).toHaveCount(0)
}

function isUnpublishedPage(failure) {
  return failure.status === PAGE_NOT_PUBLISHED.status && PAGE_NOT_PUBLISHED.path.test(failure.path)
}

function shot(page, name) {
  return page.screenshot({ path: `test-results/integration-${name}.png`, fullPage: true })
}

async function openSession(browser, key) {
  const context = await browser.newContext({ locale: 'ar-SA' })
  const page = await context.newPage()
  sessions[key] = { context, page, api: watchApi(page) }
  return sessions[key]
}

async function fillEveryEmptyScore(page, value) {
  const mains = page.locator('.criteria-tree__open')
  const count = await mains.count()
  for (let index = 0; index < count; index += 1) {
    await mains.nth(index).click()
    const inputs = page.locator('.self-main .raw-score input')
    const fields = await inputs.count()
    for (let field = 0; field < fields; field += 1) {
      const input = inputs.nth(field)
      if ((await input.inputValue()) === '') await input.fill(value)
    }
  }
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async ({ browser, request }) => {
  assertMailLogsConfigured()
  const health = await request.get('/api/v1/health', { headers: { Accept: 'application/json' } })
  expect(health.status()).toBe(200)
  expect((await health.json()).data.status).toBe('ok')
  await openSession(browser, 'admin')
  await openSession(browser, 'entity')
  await openSession(browser, 'follower')
})

test.afterAll(async () => {
  const calls = Object.fromEntries(
    Object.entries(sessions).map(([key, { api }]) => [key, api.calls]),
  )
  writeFileSync('test-results/integration-api-calls.json', JSON.stringify(calls, null, 1))
  await Promise.all(Object.values(sessions).map(({ context }) => context.close()))
})

test('the system administrator signs in with an e-mail code and enrols the authenticator', async () => {
  const { page, api } = sessions.admin
  await signInStaff(page, STAFF.systemAdmin)
  await expect(page.locator('.demo-notice--page')).toBeVisible()
  await shot(page, 'admin-dashboard-demo')
  expect(api.unexpected([GUEST_SESSION_PROBE])).toEqual([])
})

test('a second sign-in of the administrator passes the authenticator challenge', async () => {
  const { page, api } = sessions.admin
  await page.locator('.navbar__avatar').click()
  await page.getByRole('menuitem').last().click()
  await expect(page).toHaveURL(/\/auth\/login/)
  await signInStaff(page, STAFF.systemAdmin)
  expect(api.unexpected([GUEST_SESSION_PROBE])).toEqual([])
})

test('the administrator reads the published criteria tree from the server', async () => {
  const { page, api } = sessions.admin
  await page.goto('/admin/settings/criteria')
  await expect(page.locator('.criteria-tree, .criteria-version').first()).toBeVisible()
  await expect(page.locator('.demo-notice')).toHaveCount(0)
  await shot(page, 'admin-criteria')
  expect(api.unexpected([GUEST_SESSION_PROBE])).toEqual([])
})

test('the administrator sets the accredited price in the finance settings', async () => {
  const { page, api } = sessions.admin
  await page.goto('/admin/settings/finance')
  const price = page.locator('input[name="accreditedPrice"]')
  await expect(price).toBeEditable()
  await price.fill(ACCREDITED_PRICE)
  await page.locator('.settings-section .btn--primary').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  await expect(price).toHaveValue('1500.00')
  expect(api.unexpected([GUEST_SESSION_PROBE])).toEqual([])
})

test('the administrator reads the integrations overview and the payment settings', async () => {
  const { page, api } = sessions.admin
  await page.goto('/admin/settings/integrations')
  await expect(page.locator('.integrations-overview__grid').first()).toBeVisible()
  await expect(page.locator('.integrations-overview .error-state')).toHaveCount(0)
  await shot(page, 'admin-integrations')
  await page.goto('/admin/settings/payment')
  await expect(page.locator('form').first()).toBeVisible()
  await expect(page.locator('.demo-notice')).toHaveCount(0)
  expect(api.unexpected([GUEST_SESSION_PROBE])).toEqual([])
})

test('the administrator lists the award cycles', async () => {
  const { page, api } = sessions.admin
  await page.goto('/admin/award/cycles')
  await expect(page.locator('.stats-card').first()).toBeVisible()
  await expect(page.locator('.data-table, .empty-state').first()).toBeVisible()
  await shot(page, 'admin-award-cycles')
  expect(api.unexpected([GUEST_SESSION_PROBE])).toEqual([])
})

test('a new entity registers, verifies its e-mail code and opens its profile', async () => {
  const { page, api } = sessions.entity
  await registerEntity(page, ENTITY)
  await shot(page, 'entity-profile')
  expect(api.unexpected([GUEST_SESSION_PROBE])).toEqual([])
})

test('the entity starts a self assessment, submits it and sees the server result', async () => {
  test.setTimeout(180000)
  const { page, api } = sessions.entity
  await page.goto('/assessment/self')
  await page.locator('.self-start .btn--primary').click()
  await expect(page.locator('.self-summary')).toBeVisible()
  await fillEveryEmptyScore(page, '8')
  await page.locator('.self-actions .btn--secondary').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  await page.locator('.self-actions .btn--primary').click()
  const dialog = page.getByRole('dialog')
  await expect(dialog).toBeVisible()
  await dialog.locator('.btn--primary').click()
  await expect(page).toHaveURL(/\/assessment\/self\/result\/\d+$/)
  await expect(page.locator('.self-result__gauge-value')).toBeVisible()
  await shot(page, 'entity-self-result')
  expect(api.unexpected([GUEST_SESSION_PROBE])).toEqual([])
})

test('the entity sees the accredited price and its checkout reads the payment channels from the backend', async () => {
  const { page, api } = sessions.entity
  await page.goto('/assessment/accredited')
  await expect(page.locator('.accredited-price')).toContainText('1,725.00')
  await shot(page, 'entity-accredited-intro')
  const options = page.waitForResponse((response) =>
    response.url().endsWith('/api/v1/payments/checkout-options'),
  )
  await page.goto('/assessment/accredited/pay')
  expect((await options).status()).toBe(200)
  await expect(
    page.locator('.payment-notice, .method-choice, .transfer-panel').first(),
  ).toBeVisible()
  await shot(page, 'entity-checkout-boundary')
  expect(api.failures.filter((failure) => failure.path.startsWith('/api/v1/payments'))).toEqual([])
  expect(api.unexpected([GUEST_SESSION_PROBE, PAYMENT_UNAVAILABLE, PAGE_NOT_PUBLISHED])).toEqual([])
})

test('the requests follower signs in and opens the accredited request queue', async () => {
  const { page, api } = sessions.follower
  await signInStaff(page, STAFF.requestsFollower)
  await page.goto('/admin/requests')
  await expect(page.locator('.data-table, .empty-state').first()).toBeVisible()
  await expect(page.locator('.demo-notice')).toHaveCount(0)
  await shot(page, 'follower-requests')
  expect(api.unexpected([GUEST_SESSION_PROBE])).toEqual([])
})

test('demo modules answer from the mock layer with the marker while the session stays real', async () => {
  const { page, api } = sessions.entity
  await page.goto('/dashboard')
  await expect(page.locator('.demo-notice--page')).toBeVisible()
  await settled(page, api)
  await expect(page.locator('.error-state')).toHaveCount(0)
  await shot(page, 'entity-dashboard-demo')
  await page.goto('/notifications')
  await expect(page.locator('.demo-notice--page')).toBeVisible()
  await settled(page, api)
  await expect(page.locator('.error-state')).toHaveCount(0)
  await shot(page, 'entity-notifications-demo')
  await page.goto('/entity/profile')
  await expect(page.locator('.entity-profile-hub .portal-area__title')).toContainText(ENTITY.nameAr)
  expect(api.sourcesOf(/^\/api\/v1\/dashboard\//)).toEqual([SOURCES.DEMO])
  expect(api.sourcesOf(/^\/api\/v1\/notifications/)).toEqual([SOURCES.DEMO])
  expect(api.sourcesOf(/^\/api\/v1\/entities\/me$/)).toEqual([SOURCES.BACKEND])
  expect(api.sourcesOf(/^\/api\/v1\/auth\/session$/)).toEqual([SOURCES.BACKEND])
  expect(api.unexpected([GUEST_SESSION_PROBE, PAYMENT_UNAVAILABLE, PAGE_NOT_PUBLISHED])).toEqual([])
})

test('every connected administrator screen loads from the server without a contract error', async () => {
  test.setTimeout(240000)
  const { api } = sessions.admin
  const before = api.failures.length
  expect(await sweep(sessions.admin, 'admin', ADMIN_SCREENS)).toEqual([])
  expect(api.failures.slice(before).filter((failure) => !isUnpublishedPage(failure))).toEqual([])
})

test('every connected entity screen loads from the server without a contract error', async () => {
  test.setTimeout(120000)
  const { api } = sessions.entity
  const before = api.failures.length
  expect(await sweep(sessions.entity, 'entity', ENTITY_SCREENS)).toEqual([])
  expect(api.failures.slice(before).filter((failure) => !isUnpublishedPage(failure))).toEqual([])
})
