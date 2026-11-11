import { randomUUID } from 'node:crypto'
import { test, expect } from '@playwright/test'
import { closeSuccess, decisionAction } from './support/dialogs'
import { assertMailLogsConfigured } from './support/mailbox'
import { STAFF, signInStaff } from './support/accounts'
import { writeFileSync } from 'node:fs'
import {
  FOOTER_LEGAL_PROBES,
  GUEST_SESSION_PROBE,
  PAGE_NOT_PUBLISHED,
  SOURCES,
  watchApi,
} from './support/apiWatch'
import { registerEntity } from './support/entities'
import { paced } from './support/pacing'
import { AVAILABLE, PRICES_SCREEN, savePathPrice } from './support/prices'

const RUN = Date.now().toString(36)
const ENTITY = {
  email: `office.${RUN}@alnoor-association.example.sa`,
  password: `Noor-${randomUUID()}`,
  nameAr: 'جمعية النور للتنمية الأهلية',
  nameEn: 'Al-Noor Community Development Association',
  person: 'ريم بنت عبدالعزيز السبيعي',
}
const ACCREDITED_PRICE = '1500'
const ACCREDITED_QUOTE = { price_before_vat: '1500.00', total: '1725.00', currency: 'SAR' }
const PAYMENT_UNAVAILABLE = { status: 503, path: /^\/api\/v1\/payments$/ }
const GUEST_PROBES = [GUEST_SESSION_PROBE, ...FOOTER_LEGAL_PROBES]

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
  '/finance/transfers',
  '/finance/refunds',
  '/finance/payment-events',
  '/account',
]
const ENTITY_SCREENS = [
  '/paths/self',
  '/paths/accredited',
  '/paths/award',
  '/payments',
  '/certificates',
  '/bag',
  '/account',
  '/entity/profile',
]

const PUBLIC_PATH_INTROS = ['/assessment/accredited', '/assessment/advanced']
const CONNECTED_ENTITY_SCREENS = [
  ['/dashboard', /^\/api\/v1\/dashboard\//],
  ['/notifications', /^\/api\/v1\/notifications/],
]
const SHELLS = { admin: '.app-layout__content', entity: '.portal-layout__main' }

async function sweep(session, role, paths) {
  const { page } = session
  const broken = []
  for (const path of paths) {
    await openScreen(session, path, SHELLS[role])
    await shot(page, `${role}${path.replaceAll('/', '-')}`)
    if (await page.locator('.error-state').count()) broken.push(path)
  }
  return broken
}

async function visit(page, path) {
  await paced('pageLoad')
  await page.goto(path)
}

async function openScreen({ page, api }, path, shell) {
  await visit(page, path)
  await expect(page.locator(shell)).toBeVisible()
  await settled(page, api)
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
  await expect(page.locator(SHELLS.admin)).toBeVisible()
  await expect.poll(() => api.sourcesOf(/^\/api\/v1\/dashboard\//)).toEqual([SOURCES.BACKEND])
  await settled(page, api)
  await expect(page.locator('.error-state')).toHaveCount(0)
  await shot(page, 'admin-signed-in-dashboard')
  expect(api.sourcesOf(/^\/api\/v1\/auth\/session$/)).toEqual([SOURCES.BACKEND])
  expect(api.unexpected(GUEST_PROBES)).toEqual([])
})

test('a second sign-in of the administrator passes the authenticator challenge', async () => {
  const { page, api } = sessions.admin
  await page.locator('.navbar__avatar').click()
  await page.getByRole('menuitem').last().click()
  await expect(page).toHaveURL(/\/auth\/login/)
  await signInStaff(page, STAFF.systemAdmin)
  expect(api.unexpected(GUEST_PROBES)).toEqual([])
})

test('the administrator reads the published criteria tree from the server', async () => {
  const { page, api } = sessions.admin
  await visit(page, '/admin/settings/criteria')
  await expect(page.locator('.criteria-tree, .criteria-version').first()).toBeVisible()
  await shot(page, 'admin-criteria')
  expect(api.unexpected(GUEST_PROBES)).toEqual([])
})

test('the administrator sets the accredited price in the path prices', async () => {
  const { page, api } = sessions.admin
  await visit(page, PRICES_SCREEN)
  const saved = await savePathPrice(page, 'accredited', { price: ACCREDITED_PRICE })
  expect(saved.pricing).toMatchObject({ is_free: false, availability: AVAILABLE })
  expect(saved.in_force.price_before_vat).toBe(ACCREDITED_QUOTE.price_before_vat)
  await expect(
    page.locator('[data-testid="price-row-accredited"] input[name="priceBeforeVat"]'),
  ).toHaveValue('1500.00')
  expect(api.sourcesOf(/^\/api\/v1\/settings\/prices(\/accredited)?$/)).toEqual([SOURCES.BACKEND])
  expect(api.unexpected(GUEST_PROBES)).toEqual([])
})

test('the administrator reads the integrations overview and the payment settings', async () => {
  const { page, api } = sessions.admin
  await visit(page, '/admin/settings/integrations')
  await expect(page.locator('.integrations-overview__grid').first()).toBeVisible()
  await expect(page.locator('.integrations-overview .error-state')).toHaveCount(0)
  await shot(page, 'admin-integrations')
  await visit(page, '/admin/settings/payment')
  await expect(page.locator('form').first()).toBeVisible()
  expect(api.unexpected(GUEST_PROBES)).toEqual([])
})

test('the administrator lists the award cycles', async () => {
  const { page, api } = sessions.admin
  await visit(page, '/admin/award/cycles')
  await expect(page.locator('.stats-card').first()).toBeVisible()
  await expect(page.locator('.data-table, .empty-state').first()).toBeVisible()
  await shot(page, 'admin-award-cycles')
  expect(api.unexpected(GUEST_PROBES)).toEqual([])
})

test('a new entity registers, verifies its e-mail code and opens its profile', async () => {
  const { page, api } = sessions.entity
  await registerEntity(page, ENTITY)
  await shot(page, 'entity-profile')
  expect(api.unexpected(GUEST_PROBES)).toEqual([])
})

test('the entity starts a self assessment, submits it and sees the server result', async () => {
  test.setTimeout(180000)
  const { page, api } = sessions.entity
  await visit(page, '/assessment/self')
  await page.locator('.self-start .btn--primary').click()
  await expect(page.locator('.self-summary')).toBeVisible()
  await fillEveryEmptyScore(page, '8')
  await page.locator('.self-actions .btn--secondary').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  await page.locator('.self-actions .btn--primary').click()
  await decisionAction(page).click()
  await expect(page).toHaveURL(/\/assessment\/self\/result\/\d+$/)
  await closeSuccess(page)
  await expect(page.locator('.self-result__gauge-value')).toBeVisible()
  await shot(page, 'entity-self-result')
  expect(api.unexpected(GUEST_PROBES)).toEqual([])
})

test('the entity checkout reads the accredited price and the payment channels from the backend', async () => {
  const { page, api } = sessions.entity
  const options = page.waitForResponse((response) => {
    const url = new URL(response.url())
    return (
      url.pathname === '/api/v1/payments/checkout-options' &&
      url.searchParams.get('purpose') === 'accredited'
    )
  })
  await visit(page, '/assessment/accredited/pay')
  const answer = await options
  expect(answer.status()).toBe(200)
  const { pricing } = (await answer.json()).data
  expect(pricing).toMatchObject({ is_free: false, availability: AVAILABLE })
  expect(pricing.price).toMatchObject(ACCREDITED_QUOTE)
  await expect(
    page.locator('.payment-notice, .method-choice, .transfer-panel').first(),
  ).toBeVisible()
  await expect(page.locator('.checkout__aside')).toContainText('1,725.00')
  await shot(page, 'entity-checkout-boundary')
  const prices = await page.request.get('/api/v1/content/prices', {
    headers: { Accept: 'application/json' },
  })
  expect(prices.status()).toBe(200)
  const published = (await prices.json()).data
  expect(published.accredited).toMatchObject(ACCREDITED_QUOTE)
  expect(published.paths.accredited).toMatchObject({ is_free: false, availability: AVAILABLE })
  expect(published.paths.self).toMatchObject({ price: null, is_free: true })
  expect(api.sourcesOf(/^\/api\/v1\/payments\/checkout-options$/)).toEqual([SOURCES.BACKEND])
  expect(api.failures.filter((failure) => failure.path.startsWith('/api/v1/payments'))).toEqual([])
  expect(api.unexpected([...GUEST_PROBES, PAYMENT_UNAVAILABLE, PAGE_NOT_PUBLISHED])).toEqual([])
})

test('the requests follower signs in and opens the accredited request queue', async () => {
  const { page, api } = sessions.follower
  await signInStaff(page, STAFF.requestsFollower)
  await visit(page, '/admin/requests')
  await expect(page.locator('.data-table, .empty-state').first()).toBeVisible()
  await shot(page, 'follower-requests')
  expect(api.unexpected(GUEST_PROBES)).toEqual([])
})

test('connected modules answer from the server and the modules awaiting their backend show coming soon', async () => {
  const { page, api } = sessions.entity
  for (const [path, contract] of CONNECTED_ENTITY_SCREENS) {
    await openScreen(sessions.entity, path, SHELLS.entity)
    await expect.poll(() => api.sourcesOf(contract)).toEqual([SOURCES.BACKEND])
    await expect(page.locator('.error-state')).toHaveCount(0)
    await shot(page, `entity-connected${path.replaceAll('/', '-')}`)
  }
  await visit(page, '/bag')
  await expect(page.locator('.coming-soon')).toBeVisible()
  await shot(page, 'entity-bag-coming-soon')
  await visit(page, '/entity/profile')
  await expect(page.locator('.profile-header__name')).toContainText(ENTITY.nameAr)
  await expect(page.getByTestId('portal-page-title')).toHaveText('ملف الجهة')
  expect(api.sourcesOf(/^\/api\/v1\/bag\//)).toEqual([])
  expect(api.sourcesOf(/^\/api\/v1\/entities\/me$/)).toEqual([SOURCES.BACKEND])
  expect(api.sourcesOf(/^\/api\/v1\/auth\/session$/)).toEqual([SOURCES.BACKEND])
  expect(api.calls.every((call) => call.source === SOURCES.BACKEND)).toBe(true)
  expect(api.unexpected([...GUEST_PROBES, PAYMENT_UNAVAILABLE, PAGE_NOT_PUBLISHED])).toEqual([])
})

test('the platform settings screen shows coming soon to the administrator', async () => {
  const { page, api } = sessions.admin
  await visit(page, '/admin/settings/general')
  await expect(page.locator('.coming-soon')).toBeVisible()
  await shot(page, 'admin-settings-general-coming-soon')
  expect(api.sourcesOf(/^\/api\/v1\/settings\/general$/)).toEqual([])
  expect(api.unexpected(GUEST_PROBES)).toEqual([])
})

test('the public path intros stay open to the signed-in entity while the homepage is unpublished', async () => {
  const { page, api } = sessions.entity
  const status = await page.request.get('/api/v1/content/status', {
    headers: { Accept: 'application/json' },
  })
  expect((await status.json()).data.maintenance).toBe(true)
  for (const path of PUBLIC_PATH_INTROS) {
    await visit(page, path)
    await expect(page).not.toHaveURL(/\/maintenance$/)
    await expect(page.locator('.maintenance__title')).toHaveCount(0)
  }
  await settled(page, api)
  expect(api.unexpected([...GUEST_PROBES, PAGE_NOT_PUBLISHED])).toEqual([])
})

test('every connected administrator screen loads from the server without a contract error', async () => {
  test.setTimeout(360000)
  const { api } = sessions.admin
  const before = api.failures.length
  expect(await sweep(sessions.admin, 'admin', ADMIN_SCREENS)).toEqual([])
  expect(api.failures.slice(before).filter((failure) => !isUnpublishedPage(failure))).toEqual([])
})

test('every connected entity screen loads from the server without a contract error', async () => {
  test.setTimeout(180000)
  const { api } = sessions.entity
  const before = api.failures.length
  expect(await sweep(sessions.entity, 'entity', ENTITY_SCREENS)).toEqual([])
  expect(api.failures.slice(before).filter((failure) => !isUnpublishedPage(failure))).toEqual([])
})
