import { randomUUID } from 'node:crypto'
import { test, expect } from '@playwright/test'
import {
  assertMailLogsConfigured,
  mailMark,
  waitForInvitationToken,
  waitForResetToken,
} from './support/mailbox'
import { STAFF, rememberPassword, signInStaff } from './support/accounts'
import { FOOTER_LEGAL_PROBES, GUEST_SESSION_PROBE, SOURCES, watchApi } from './support/apiWatch'
import { apiOk } from './support/api'
import { AVAILABLE, savePathPrice } from './support/prices'
import { paced } from './support/pacing'
import { registerEntity } from './support/entities'
import { squarePng } from './support/images'

const RUN = Date.now().toString(36)
const INVITED = { email: `reviewer.${RUN}@assessors.example.sa`, name: 'د. منى بنت فيصل العنزي' }
const NEW_PASSWORD = `Moashir-${randomUUID()}`
const LICENSE = String(Date.now()).slice(-9)
const ENTITY = {
  email: `office.${RUN}@alnoor-waqf.example.sa`,
  password: `Noor-${randomUUID()}`,
  nameAr: 'وقف النور التعليمي',
  nameEn: 'Al-Noor Educational Waqf',
  person: 'سلمان بن فهد العتيبي',
}
const ALLOWED = [GUEST_SESSION_PROBE, ...FOOTER_LEGAL_PROBES]
const ROWS = '.table:not(.table--loading) tbody .table__row'
const PACED_TIMEOUT_MS = 180 * 1000
const RETRY_INTERVALS_MS = [10 * 1000, 20 * 1000]
const sessions = {}

test.describe.configure({ mode: 'serial' })

async function openSession(browser, key) {
  const context = await browser.newContext({ locale: 'ar-SA' })
  const page = await context.newPage()
  sessions[key] = { context, page, api: watchApi(page) }
  return sessions[key]
}

function shot(page, name) {
  return page.screenshot({ path: `test-results/integration-37c-${name}.png`, fullPage: true })
}

function probe(page, path) {
  const origin = new URL(page.url()).origin
  return page.request.get(`/api/v1${path}`, {
    headers: { Accept: 'application/json', Referer: `${origin}/`, Origin: origin },
  })
}

async function probeWrite(page, path, body) {
  const origin = new URL(page.url()).origin
  const cookies = await page.context().cookies(origin)
  const xsrf = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value ?? ''
  return page.request.put(`/api/v1${path}`, {
    data: body,
    headers: {
      Accept: 'application/json',
      Referer: `${origin}/`,
      Origin: origin,
      'X-XSRF-TOKEN': decodeURIComponent(xsrf),
    },
  })
}

async function userIdOf(page, email) {
  const { data } = await apiOk(page, 'GET', `/users?search=${encodeURIComponent(email)}`)
  return data.find((user) => user.email === email).id
}

test.beforeAll(async ({ browser }) => {
  assertMailLogsConfigured()
  const admin = await openSession(browser, 'admin')
  await signInStaff(admin.page, STAFF.systemAdmin)
})

test.afterAll(async () => {
  await Promise.all(Object.values(sessions).map((session) => session.context.close()))
})

test('the System Administrator manages qualifications and the follower scope on the real users contract', async () => {
  const { page, api: watch } = sessions.admin
  const assessorId = await userIdOf(page, STAFF.assessor)
  const followerId = await userIdOf(page, STAFF.requestsFollower)
  const qualified = await apiOk(page, 'GET', '/requests/assessors?path=accredited')
  expect(qualified.data.map((person) => person.id)).toContain(assessorId)
  await page.goto('/admin/settings/users')
  await page.locator('.toolbar__search input').fill('assessor@')
  const row = page.locator(ROWS).filter({ hasText: STAFF.assessor })
  await expect(row.locator('.path-badge')).toHaveCount(2)
  await row.locator('.dropdown-menu > button').click()
  await page.locator('[role="menuitem"]', { hasText: 'مسارات التأهيل' }).click()
  await page.locator('.modal .checkbox__input').nth(1).uncheck()
  await page.locator('.modal button[type="submit"]').click()
  await expect(row.locator('.path-badge')).toHaveCount(1)
  const advanced = await apiOk(page, 'GET', '/requests/assessors?path=advanced')
  expect(advanced.data.map((person) => person.id)).not.toContain(assessorId)
  await page.locator('.toolbar__search input').fill('requests.follower@')
  const follower = page.locator(ROWS).filter({ hasText: STAFF.requestsFollower })
  await follower.locator('.dropdown-menu > button').click()
  await page.locator('[role="menuitem"]', { hasText: 'نطاق المتابعة' }).click()
  await page.locator('.modal .radio__input').nth(1).check()
  await page.locator('.modal .checkbox__input').first().check()
  await page.locator('.modal button[type="submit"]').click()
  await expect(follower.locator('.path-badge--accredited')).toBeVisible()
  const updated = await apiOk(page, 'GET', `/users/${followerId}`)
  expect(updated.data.request_scope).toEqual({ all_paths: false, paths: ['accredited'] })
  await shot(page, 'users')
  expect(
    watch.sourcesOf(/^\/api\/v1\/users\/\d+\/(assessor-qualifications|request-scope)$/),
  ).toEqual([SOURCES.BACKEND])
  expect(watch.unexpected(ALLOWED)).toEqual([])
})

test('the queue serves one path per call with the counts of the followed paths', async () => {
  const { page, api: watch } = sessions.admin
  const queueCall = page.waitForResponse((response) => /\/api\/v1\/requests\?/.test(response.url()))
  await page.goto('/admin/requests')
  const body = await (await queueCall).json()
  expect(body.meta.path).toBe('accredited')
  expect(body.meta.path_counts.map((entry) => entry.path)).toEqual(['accredited', 'advanced'])
  await expect(page.locator('.sidebar__item--accredited, .sidebar__item--advanced')).toHaveCount(2)
  await page.locator('.path-switch__link').nth(1).click()
  await expect(page).toHaveURL(/path=advanced/)
  await expect(page.locator('.requests-page__title-row .path-badge--advanced')).toBeVisible()
  await shot(page, 'queue')
  expect(watch.unexpected(ALLOWED)).toEqual([])
  expect((await probe(page, '/requests?filter[path]=self')).status()).toBe(422)
})

test('the per-path and certificate settings save on the real contract', async () => {
  const { page, api: watch } = sessions.admin
  await page.goto('/admin/settings/assessment')
  await expect(page.locator('input[name="reviewSlaDays"]')).toBeVisible()
  const intro = 'يقيّم مقيّم معتمد شواهد الجهة لكل معيار فرعي قبل اعتماد النتيجة.'
  await page.locator('textarea[name="entityTexts.intro.ar"]').fill(intro)
  await page
    .locator('textarea[name="entityTexts.intro.en"]')
    .fill(
      'An accredited assessor reviews the evidence of every sub-criterion before the result is approved.',
    )
  await page.locator('.path-settings .btn--primary').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  const guide = await apiOk(page, 'GET', '/content/assessment-paths/accredited')
  expect(guide.data.entity_texts.intro.ar).toBe(intro)
  await expect(page.locator('input[name="price"]')).toHaveCount(0)
  const refused = await probeWrite(page, '/settings/assessment/paths/accredited', {
    price: '1150.00',
  })
  expect(refused.status()).toBe(422)
  const refusal = await refused.json()
  expect(refusal.code).toBe('validation_failed')
  expect(Object.keys(refusal.errors)).toEqual(['price'])
  await page.locator('[data-testid="path-price-moved"] a').click()
  await expect(page).toHaveURL(/\/admin\/settings\/prices$/)
  const saved = await savePathPrice(page, 'accredited', { price: '1150' })
  expect(saved.pricing).toMatchObject({ is_free: false, availability: AVAILABLE })
  await page.goto('/admin/settings/assessment')
  await expect(page.locator('.path-settings .pricing-breakdown')).toBeVisible()
  await expect(page.locator('input[name="price"]')).toHaveCount(0)
  const priced = await apiOk(page, 'GET', '/settings/assessment/paths/accredited')
  expect(priced.data.price.price_before_vat).toBe('1150.00')
  await page.locator('[role="tab"]').nth(1).click()
  await expect(page.locator('input[name="price"]')).toHaveCount(0)
  await page.goto('/admin/settings/certificates')
  await page.locator('input[name="levelThresholds.bronze"]').fill('61')
  await page.locator('button[form="certificate-settings-form"]').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  const thresholds = await apiOk(page, 'GET', '/settings/certificates')
  expect(thresholds.data.level_thresholds.bronze).toBe('61.00')
  await shot(page, 'certificate-settings')
  await page.locator('input[name="levelThresholds.bronze"]').fill('60')
  await page.locator('button[form="certificate-settings-form"]').click()
  await expect(page.locator('input[name="levelThresholds.bronze"]')).toHaveValue('60.00')
  expect(
    watch.sourcesOf(/^\/api\/v1\/settings\/(assessment\/paths\/accredited|certificates)$/),
  ).toEqual([SOURCES.BACKEND])
  expect(watch.unexpected(ALLOWED)).toEqual([])
})

test('a scoped follower sees one path only', async ({ browser }) => {
  const follower = await openSession(browser, 'follower')
  await signInStaff(follower.page, STAFF.requestsFollower)
  await follower.page.goto('/admin/requests?path=advanced')
  await expect(
    follower.page.locator('.requests-page__title-row .path-badge--accredited'),
  ).toBeVisible()
  await expect(follower.page.locator('.sidebar__item--advanced')).toHaveCount(0)
  expect(follower.api.unexpected(ALLOWED)).toEqual([])
  expect((await probe(follower.page, '/requests?filter[path]=advanced')).status()).toBe(422)
})

test('an invited staff member activates the account from the e-mailed link', async ({
  browser,
}) => {
  test.setTimeout(PACED_TIMEOUT_MS)
  const { page } = sessions.admin
  const mark = mailMark(INVITED.email)
  await apiOk(page, 'POST', '/users', {
    body: { name: INVITED.name, email: INVITED.email, roles: ['assessor'] },
  })
  const token = await waitForInvitationToken(INVITED.email, mark)
  const invited = await openSession(browser, 'invited')
  await invited.page.goto(`/auth/invitations/${token}`)
  await invited.page.locator('[name="password"]').fill(NEW_PASSWORD)
  await invited.page.locator('[name="passwordConfirmation"]').fill(NEW_PASSWORD)
  const terms = invited.page.locator('[name="termsAccepted"]')
  await terms.check()
  await shot(invited.page, 'invitation')
  await paced('mfa')
  await invited.page.locator('form button[type="submit"]').click()
  await expect(invited.page).toHaveURL(/\/auth\/mfa/)
  await invited.page.goto(`/auth/invitations/${token}`)
  await invited.page.locator('[name="password"]').fill(NEW_PASSWORD)
  await invited.page.locator('[name="passwordConfirmation"]').fill(NEW_PASSWORD)
  await terms.check()
  await invited.page.locator('form button[type="submit"]').click()
  await expect(invited.page.locator('.auth-status--danger')).toBeVisible()
})

test('a forgotten password is reset from the e-mailed link and works at sign in', async ({
  browser,
}) => {
  test.setTimeout(PACED_TIMEOUT_MS)
  const guest = await openSession(browser, 'guest')
  await guest.page.goto('/auth/login')
  await guest.page.locator('.auth-form__forgot').click()
  const mark = mailMark(STAFF.contentOfficer)
  await guest.page.locator('[name="email"]').fill(STAFF.contentOfficer)
  await guest.page.locator('form button[type="submit"]').click()
  await expect(guest.page.locator('.auth-status--success')).toBeVisible()
  const token = await waitForResetToken(STAFF.contentOfficer, mark)
  await guest.page.goto(`/auth/reset-password/${token}`)
  await guest.page.locator('[name="email"]').fill(STAFF.contentOfficer)
  await guest.page.locator('[name="password"]').fill(NEW_PASSWORD)
  await guest.page.locator('[name="passwordConfirmation"]').fill(NEW_PASSWORD)
  await guest.page.locator('form button[type="submit"]').click()
  await expect(guest.page).toHaveURL(/\/auth\/login\?notice=password-reset/)
  rememberPassword(STAFF.contentOfficer, NEW_PASSWORD)
  await shot(guest.page, 'reset-done')
  await expect(async () => {
    await guest.page.goto('/auth/login')
    await expect(guest.page.locator('[name="password"]')).toBeVisible({ timeout: 3000 })
  }).toPass({ intervals: RETRY_INTERVALS_MS, timeout: PACED_TIMEOUT_MS / 2 })
  await guest.page.locator('[name="identifier"]').fill(STAFF.contentOfficer)
  await guest.page.locator('[name="password"]').fill(NEW_PASSWORD)
  await paced('mfa')
  await guest.page.locator('form button[type="submit"]').click()
  await expect(guest.page).toHaveURL(/\/auth\/mfa/)
  expect(guest.api.unexpected(ALLOWED)).toEqual([])
})

test('the staff entity detail shows the registry details and the logo of the entity', async ({
  browser,
}) => {
  const owner = await openSession(browser, 'owner')
  await registerEntity(owner.page, ENTITY)
  const identity = owner.page.locator('[data-section="identity"]')
  await identity.locator('.profile-section__edit').click()
  await identity.locator('input[name="licenseNumber"]').fill(LICENSE)
  await identity.locator('input[name="foundedOn"]').fill('2014-09-01')
  await identity.locator('button[type="submit"]').click()
  await expect(identity.locator('.profile-section__status-text--success')).toBeVisible()
  await owner.page.getByTestId('profile-change-logo').click()
  await owner.page
    .getByTestId('logo-dialog')
    .locator('.logo-dialog__input')
    .setInputFiles({ name: 'alnoor-logo.png', mimeType: 'image/png', buffer: squarePng(240) })
  await expect(owner.page.locator('.profile-header__logo-image')).toBeVisible()
  await owner.page.keyboard.press('Escape')
  const { page, api: watch } = sessions.admin
  const { data } = await apiOk(page, 'GET', `/entities?search=${encodeURIComponent(ENTITY.email)}`)
  await page.goto(`/admin/entities/${data[0].id}`)
  const details = page.locator('.entity-record__grid')
  await expect(details).toContainText(LICENSE)
  await expect(details).toContainText('2014-09-01')
  const logo = page.locator('.entity-logo-preview__image')
  await expect(logo).toBeVisible()
  await expect.poll(() => logo.evaluate((image) => image.naturalWidth)).toBe(240)
  await expect(page.locator('.profile-history')).toContainText('رقم الترخيص')
  await shot(page, 'entity-detail')
  expect(watch.unexpected(ALLOWED)).toEqual([])
})
