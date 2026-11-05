import { Buffer } from 'node:buffer'
import { writeFileSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { assertMailLogsConfigured } from './support/mailbox'
import { STAFF, signInStaff } from './support/accounts'
import { FOOTER_LEGAL_PROBES, GUEST_SESSION_PROBE, PAGE_NOT_PUBLISHED, SOURCES, watchApi } from './support/apiWatch'

const RUN = Date.now().toString(36)
const HERO = {
  key: `hero_${RUN}`,
  titleAr: 'قِس تميّز جهتك المؤسسي',
  titleEn: 'Measure your institutional excellence',
  descriptionAr: 'منصة مؤشر تقيس نضج الجهات غير الربحية وفق نموذج التميز في العمل الخيري.',
}
const KPI = {
  nameAr: `عدد الجمعيات المشاركة ${RUN}`,
  nameEn: `Participating associations ${RUN}`,
  value: '486',
  target: '700',
}
const PIXEL_PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
  'base64',
)

const sessions = {}

function shot(page, name) {
  return page.screenshot({ path: `test-results/integration-content-${name}.png`, fullPage: true })
}

async function openSession(browser, key) {
  const context = await browser.newContext({ locale: 'ar-SA' })
  const page = await context.newPage()
  sessions[key] = { context, page, api: watchApi(page) }
  return sessions[key]
}

async function confirmDialog(page) {
  await page.locator('.modal .modal__footer .btn').last().click()
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async ({ browser }) => {
  assertMailLogsConfigured()
  await openSession(browser, 'officer')
  await openSession(browser, 'guest')
})

test.afterAll(async () => {
  const calls = Object.fromEntries(Object.entries(sessions).map(([key, { api }]) => [key, api.calls]))
  writeFileSync('test-results/integration-content-api-calls.json', JSON.stringify(calls, null, 1))
  await Promise.all(Object.values(sessions).map(({ context }) => context.close()))
})

test('a guest sees the maintenance page while the homepage is not published', async () => {
  const { page, api } = sessions.guest
  await page.goto('/')
  await expect(page).toHaveURL(/\/maintenance$/)
  await expect(page.locator('.maintenance__title')).toBeVisible()
  expect(api.sourcesOf(/^\/api\/v1\/content\/status$/)).toEqual([SOURCES.BACKEND])
  expect(api.unexpected([GUEST_SESSION_PROBE, ...FOOTER_LEGAL_PROBES])).toEqual([])
})

test('the Content Officer builds the hero with an uploaded image and publishes the homepage', async () => {
  const { page, api } = sessions.officer
  await signInStaff(page, STAFF.contentOfficer)
  await page.goto('/admin/settings/homepage')
  await expect(page.locator('.publication-control .badge')).toHaveClass(/badge--warning/)
  await expect(page.locator('.demo-notice')).toHaveCount(0)

  await page.locator('.cms-page-editor__toolbar .btn--primary').click()
  const dialog = page.locator('.modal').last()
  await dialog.locator('input[name="key"]').fill(HERO.key)
  await dialog.locator('select[name="type"]').selectOption('hero')
  await dialog.locator('input[name="title.ar"]').fill(HERO.titleAr)
  await dialog.locator('input[name="title.en"]').fill(HERO.titleEn)
  await dialog.locator('textarea[name="description.ar"]').fill(HERO.descriptionAr)
  await dialog.locator('input[type="file"]').setInputFiles({ name: 'hero.png', mimeType: 'image/png', buffer: PIXEL_PNG })
  await expect(dialog.locator('.content-image-field__image')).toHaveAttribute('src', /\/api\/v1\/content\/files\/[0-9a-f-]{36}$/)
  await dialog.locator('.modal__footer .btn--primary').click()
  await expect(dialog).toBeHidden()
  await expect(page.locator('.cms-section-row__key')).toContainText([HERO.key])
  await shot(page, 'editor-draft')

  await page.locator('.publication-control .btn--primary').click()
  await confirmDialog(page)
  await expect(page.locator('.publication-control .badge')).toHaveClass(/badge--success/)
  await shot(page, 'editor-published')

  const published = api.calls.filter((call) => call.method === 'POST' && call.path === '/api/v1/admin/content/pages/home/publish')
  expect(published).toEqual([{ method: 'POST', path: '/api/v1/admin/content/pages/home/publish', status: 200, source: SOURCES.BACKEND }])
  expect(api.unexpected([GUEST_SESSION_PROBE, ...FOOTER_LEGAL_PROBES])).toEqual([])
})

test('the published homepage reaches the public site with the server image', async () => {
  const { page, api } = sessions.guest
  await page.goto('/')
  await expect(page).toHaveURL(/\/$/)
  await expect(page.locator('.home-hero__title')).toContainText(HERO.titleAr)
  const image = page.locator('.home-hero__media img')
  await expect(image).toHaveAttribute('src', /\/api\/v1\/content\/files\/[0-9a-f-]{36}$/)
  await expect.poll(() => image.evaluate((element) => element.naturalWidth)).toBe(1)
  await shot(page, 'public-home')
  expect(api.sourcesOf(/^\/api\/v1\/content\/home$/)).toEqual([SOURCES.BACKEND])
  expect(api.unexpected([GUEST_SESSION_PROBE, PAGE_NOT_PUBLISHED, ...FOOTER_LEGAL_PROBES])).toEqual([])
})

test('the Content Officer creates, values and publishes a KPI', async () => {
  const { page, api } = sessions.officer
  await page.goto('/admin/kpis')
  await expect(page.locator('.stats-card').first()).toBeVisible()
  await page.locator('.toolbar__actions .btn--primary').click()
  const form = page.locator('.modal').last()
  await form.locator('input[name="name.ar"]').fill(KPI.nameAr)
  await form.locator('input[name="name.en"]').fill(KPI.nameEn)
  await form.locator('input[name="value"]').fill(KPI.value)
  await form.locator('input[name="target"]').fill(KPI.target)
  await form.locator('.modal__footer .btn--primary').click()
  await expect(form).toBeHidden()
  const row = page.locator('.table__row', { hasText: KPI.nameAr })
  await expect(row).toBeVisible()

  await row.locator('.actions-dropdown > button').click()
  await page.locator('[role="menuitem"]').nth(1).click()
  const values = page.locator('.modal', { has: page.locator('.kpi-values') })
  await values.locator('input[name="value"]').fill('402')
  await values.locator('input[name="measuredOn"]').fill('2026-01-31')
  await values.locator('.kpi-values__form .btn--primary').click()
  await expect(values.locator('.kpi-history__row')).toHaveCount(2)
  await values.locator('.modal__backdrop').click({ position: { x: 8, y: 8 } })
  await expect(values).toBeHidden()

  await row.locator('.actions-dropdown > button').click()
  await page.locator('[role="menuitem"]').nth(2).click()
  await confirmDialog(page)
  await expect(row.locator('.badge--success')).toBeVisible()
  await shot(page, 'kpi-admin')
  expect(api.unexpected([GUEST_SESSION_PROBE, ...FOOTER_LEGAL_PROBES])).toEqual([])
})

test('a guest opens the KPI by its slug and reads the value history from the server', async () => {
  const { page, api } = sessions.guest
  await page.goto('/kpis')
  const card = page.locator('.kpi-card', { hasText: KPI.nameAr })
  await expect(card).toBeVisible()
  await card.click()
  await expect(page).toHaveURL(/\/kpis\/[a-z0-9-]+$/)
  await expect(page.locator('.kpi-detail__title')).toHaveText(KPI.nameAr)
  await expect(page.locator('.kpi-detail__number')).toHaveText(KPI.value)
  await expect(page.locator('progress.kpi-detail__meter')).toHaveAttribute('value', '69')
  await expect(page.locator('.kpi-history__row')).toHaveCount(2)
  await expect(page.locator('.kpi-history__row').first()).toContainText(KPI.value)
  await shot(page, 'kpi-detail')
  expect(api.sourcesOf(/^\/api\/v1\/kpis\/[a-z0-9-]+$/)).toEqual([SOURCES.BACKEND])
  expect(api.unexpected([GUEST_SESSION_PROBE, PAGE_NOT_PUBLISHED, ...FOOTER_LEGAL_PROBES])).toEqual([])
})
