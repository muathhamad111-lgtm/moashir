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

const RUN = Date.now().toString(36)
const ENTITY = {
  email: `office.${RUN}@alwafa-association.example.sa`,
  password: `Wafa-${randomUUID()}`,
  nameAr: 'جمعية الوفاء للتنمية الأسرية',
  nameEn: 'Al-Wafa Family Development Association',
  person: 'هند بنت سعد المطيري',
}

const PORTAL_CONTRACTS = [
  /^\/api\/v1\/dashboard\/summary$/,
  /^\/api\/v1\/dashboard\/action-center$/,
  /^\/api\/v1\/notifications$/,
  /^\/api\/v1\/notifications\/unread-count$/,
  /^\/api\/v1\/me\/contact-messages$/,
  /^\/api\/v1\/assessments\/accredited\/current$/,
]

const NEW_ENTITY_TILES = { self: 'start', accredited: 'unavailable', award: 'none' }

const session = {}

test.describe.configure({ mode: 'serial' })

async function settled(page) {
  await session.api.idle()
  await expect(page.locator('[aria-busy="true"], .skeleton')).toHaveCount(0)
}

function shot(page, name) {
  return page.screenshot({ path: `test-results/integration-portal-${name}.png`, fullPage: true })
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

test('a new entity lands in the portal shell with its path tiles on the real contracts', async () => {
  const { page, api } = session
  await registerEntity(page, ENTITY)
  const summary = page.waitForResponse((response) => {
    const url = new URL(response.url())
    return (
      url.pathname === '/api/v1/dashboard/summary' && url.searchParams.get('board') === 'entity'
    )
  })
  await page.goto('/dashboard')
  const { data: board } = await (await summary).json()
  await settled(page)
  await expect(page.locator('.portal-top-bar')).toBeVisible()
  await expect(page.locator('.sidebar')).toHaveCount(0)
  const paths = board.widgets.find((widget) => widget.key === 'paths').data
  expect(paths.self.pricing).toMatchObject({
    price: null,
    is_free: true,
    availability: 'available',
  })
  expect(paths.accredited.pricing).toMatchObject({ price: null, availability: 'paused' })
  for (const [key, state] of Object.entries(NEW_ENTITY_TILES)) {
    expect(paths[key].is_listed).toBe(true)
    await expect(page.locator(`[data-testid="path-tile-${key}"]`)).toHaveAttribute(
      'data-state',
      state,
    )
  }
  await expect(page.locator('[data-testid="path-tile-self"] .path-price__free')).toBeVisible()
  await expect(
    page.locator('[data-testid="path-tile-accredited"] .path-price__muted'),
  ).toBeVisible()
  await expect(page.locator('[data-testid="next-step"] .next-step__title')).toBeVisible()
  await expect(page.locator('[data-testid="attention-strip"]')).toContainText('%')
  for (const path of PORTAL_CONTRACTS) expect(api.sourcesOf(path)).toEqual([SOURCES.BACKEND])
  await shot(page, 'home')
  expect(api.unexpected([GUEST_SESSION_PROBE, ...FOOTER_LEGAL_PROBES])).toEqual([])
})

test('the notification centre and the preferences use the real notification contract', async () => {
  const { page, api } = session
  await page.goto('/notifications')
  await settled(page)
  await expect(page.locator('[role="tablist"] [role="tab"]')).toHaveCount(2)
  await expect(page.locator('.notification-center-item, .empty-state').first()).toBeVisible()
  await page.locator('.notification-preferences-card .btn').click()
  const dialog = page.locator('.modal__panel')
  await expect(dialog.locator('.notification-preference-row').first()).toBeVisible()
  await dialog.locator('.notification-preference-row .checkbox:not(.is-disabled)').first().click()
  await dialog.locator('.modal__footer .btn--primary').click()
  await expect(page.locator('.modal__panel')).toHaveCount(0)
  await expect(page.locator('.toast--success').last()).toBeVisible()
  expect(api.sourcesOf(/^\/api\/v1\/me\/notification-preferences$/)).toEqual([SOURCES.BACKEND])
  await shot(page, 'notifications')
  expect(api.unexpected([GUEST_SESSION_PROBE, ...FOOTER_LEGAL_PROBES])).toEqual([])
})

test('the More screen, the help page and the accredited entry stay on the real backend', async () => {
  const { page, api } = session
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/more')
  await expect(page.locator('.portal-more__identity')).toContainText(ENTITY.nameAr)
  const services = page.locator('.portal-more__group').first()
  await expect(services.locator('.portal-menu-entry__value').last()).toContainText('%')
  await settled(page)
  await expect(page.locator('.mobile-tab-bar')).toBeVisible()
  await shot(page, 'more')
  await page.goto('/help')
  await expect(page.locator('.path-guide-card')).toHaveCount(2)
  await expect(page.locator('.path-guide-card .skeleton')).toHaveCount(0)
  await settled(page)
  expect(api.sourcesOf(/^\/api\/v1\/content\/assessment-paths\//)).toEqual([SOURCES.BACKEND])
  await page.goto('/assessment/accredited')
  await settled(page)
  await expect(page).toHaveURL(/\/assessment\/accredited$/)
  await expect(page.locator('.accredited-intro__title')).toBeVisible()
  await expect(page.locator('.accredited-intro .error-state')).toHaveCount(0)
  expect(api.unexpected([GUEST_SESSION_PROBE, PAGE_NOT_PUBLISHED, ...FOOTER_LEGAL_PROBES])).toEqual(
    [],
  )
})
