import { execFileSync } from 'node:child_process'
import { randomUUID } from 'node:crypto'
import { resolve } from 'node:path'
import process from 'node:process'
import { test, expect } from '@playwright/test'
import { assertMailLogsConfigured, mailMark, waitForCode } from './support/mailbox'
import {
  STAFF,
  requestSignInCode,
  resetPassword,
  signInStaff,
  signInWithCode,
  signInWithPassword,
  typeCode,
} from './support/accounts'
import { FOOTER_LEGAL_PROBES, GUEST_SESSION_PROBE, SOURCES, watchApi } from './support/apiWatch'
import { api } from './support/api'
import { nextUnifiedNumber, registerEntity } from './support/entities'

const RUN = Date.now().toString(36)
const MOBILE = `0500000${String(Date.now()).slice(-3)}`
const ENTITY = {
  email: `office.${RUN}@altamkeen-assoc.example.sa`,
  password: `Tamkeen-${randomUUID()}`,
  nameAr: 'جمعية التمكين الأسري',
  nameEn: 'Al-Tamkeen Family Association',
  person: 'منيرة بنت سالم الدوسري',
  unifiedNumber: nextUnifiedNumber(),
}
const WRONG_PASSWORD = `Wrong-${randomUUID()}`
const BACKEND_SCRIPT = resolve(process.cwd(), 'scripts/integration/backend.sh')
const ALLOWED = [
  GUEST_SESSION_PROBE,
  ...FOOTER_LEGAL_PROBES,
  { status: 422, path: /^\/api\/v1\/auth\/login$/ },
  { status: 422, path: /^\/api\/v1\/auth\/register$/ },
]
const PACED_TIMEOUT_MS = 240 * 1000
const SHOTS = 'e2e/__screenshots__/signin'
const sessions = {}

test.describe.configure({ mode: 'serial' })

async function openSession(browser, key) {
  const context = await browser.newContext({ locale: 'ar-SA' })
  const page = await context.newPage()
  sessions[key] = { context, page, api: watchApi(page) }
  return sessions[key]
}

async function shot(page, name) {
  await page.evaluate(() => window.scrollTo(0, 0))
  await page.screenshot({ path: `${SHOTS}/integration-${name}.png`, fullPage: true })
}

async function signOut({ page, api: watch }) {
  const before = watch.failures.length
  await page.locator('.account-menu__chip').click()
  await page.locator('.account-menu__panel .portal-menu-entry--danger').click()
  await expect(page).toHaveURL(/\/auth\/login/)
  await watch.idle()
  const afterSignOut = watch.failures.splice(before)
  expect(afterSignOut.filter((failure) => failure.status !== 401)).toEqual([])
}

async function expectPortal(page) {
  await expect(page.locator('.portal-top-bar')).toBeVisible()
  await expect(page).toHaveURL(/\/dashboard/)
}

test.beforeAll(async ({ browser }) => {
  assertMailLogsConfigured()
  await openSession(browser, 'entity')
  await openSession(browser, 'admin')
})

test.afterAll(async () => {
  await Promise.all(Object.values(sessions).map((session) => session.context.close()))
})

test('a new entity registers with its unified number, and the number cannot be used twice', async () => {
  const { page, api: watch } = sessions.entity
  await registerEntity(page, ENTITY)
  await expect(page.locator('[name="unifiedNumber"]')).toHaveValue(ENTITY.unifiedNumber)
  expect(watch.sourcesOf(/^\/api\/v1\/auth\/register$/)).toEqual([SOURCES.BACKEND])
  await shot(page, 'profile-unified-number')
  await signOut(sessions.entity)
  const duplicate = await api(page, 'POST', '/auth/register', {
    body: { unified_number: ENTITY.unifiedNumber },
  })
  expect(duplicate.status).toBe(422)
  expect(duplicate.body.errors.unified_number[0]).toContain('مسجّل لجهة أخرى')
})

test('the entity signs in with the e-mail and its password, then with the unified number and a code', async () => {
  test.setTimeout(PACED_TIMEOUT_MS)
  const { page, api: watch } = sessions.entity
  await signInWithPassword(page, ENTITY.email, WRONG_PASSWORD)
  await expect(page.locator('.auth-failure[data-failure="credentials"]')).toBeVisible()
  await signInWithPassword(page, ENTITY.email, ENTITY.password)
  await expectPortal(page)
  await signOut(sessions.entity)
  const mark = mailMark(ENTITY.email)
  await requestSignInCode(page, ENTITY.unifiedNumber)
  await expect(page).toHaveURL(/\/auth\/otp/)
  await expect(page.locator('.auth-otp__target')).toHaveCount(0)
  await expect(page.locator('.auth-otp__password')).toBeVisible()
  await shot(page, 'otp-unified-number')
  await typeCode(page, await waitForCode(ENTITY.email, mark))
  await expectPortal(page)
  expect(watch.sourcesOf(/^\/api\/v1\/auth\/(login|otp\/request|otp\/verify)$/)).toEqual([
    SOURCES.BACKEND,
  ])
  expect(watch.unexpected(ALLOWED)).toEqual([])
})

test('the entity saves a mobile number and, once verified, signs in with it and its password or a code', async () => {
  test.setTimeout(PACED_TIMEOUT_MS)
  const { page, api: watch } = sessions.entity
  await page.goto('/entity/profile?tab=contact')
  const card = page.locator('[data-testid="phone-verification"]')
  await expect(card).toBeVisible()
  await page.locator('[name="phone"]').fill(MOBILE)
  await page.locator('.portal-area__actions .btn--primary').click()
  await expect(card.locator('.badge--warning')).toBeVisible()
  const sent = page.waitForResponse(
    (response) => new URL(response.url()).pathname === '/api/v1/me/phone/verification',
  )
  await card.locator('button').click()
  expect((await sent).status()).toBe(200)
  await expect(card.locator('.otp-input__box')).toHaveCount(6)
  await shot(page, 'phone-code-sent')
  await signOut(sessions.entity)
  await signInWithPassword(page, MOBILE, ENTITY.password)
  await expect(page.locator('.auth-failure[data-failure="credentials"]')).toBeVisible()
  execFileSync(BACKEND_SCRIPT, ['verify-phone', ENTITY.email], { stdio: 'ignore' })
  await signInWithPassword(page, MOBILE, ENTITY.password)
  await expectPortal(page)
  await page.goto('/entity/profile?tab=contact')
  await expect(page.locator('[data-testid="phone-verification"] .badge--success')).toBeVisible()
  await signOut(sessions.entity)
  await signInWithCode(page, MOBILE, ENTITY.email)
  await expectPortal(page)
  expect(watch.unexpected(ALLOWED)).toEqual([])
  await signOut(sessions.entity)
})

test('a staff identifier gets the same code answer as an unknown one, and the administrator sees the channels', async () => {
  test.setTimeout(PACED_TIMEOUT_MS)
  const admin = sessions.admin
  const { page } = sessions.entity
  const staffAnswer = await api(page, 'POST', '/auth/otp/request', {
    body: { identifier: STAFF.systemAdmin },
  })
  const unknownAnswer = await api(page, 'POST', '/auth/otp/request', {
    body: { identifier: `nobody.${RUN}@example.sa` },
  })
  for (const answer of [staffAnswer, unknownAnswer]) {
    expect(answer.status).toBe(200)
    expect(answer.body.data.step).toBe('otp_required')
  }
  expect(staffAnswer.body.data.message).toBe(unknownAnswer.body.data.message)
  expect(staffAnswer.body.data.otp.channel).toBe(unknownAnswer.body.data.otp.channel)
  await resetPassword(admin.page, STAFF.systemAdmin)
  await signInStaff(admin.page, STAFF.systemAdmin)
  await admin.page.goto('/admin/settings/sign-in')
  await expect(admin.page.locator('.sign-in-channels')).toBeVisible()
  await expect(admin.page.locator('input[name="codeChannelEmail"]')).toBeChecked()
  await expect(admin.page.locator('input[name="codeChannelEmail"]')).toBeDisabled()
  await expect(admin.page.locator('.sign-in-security__statement')).toBeVisible()
  await expect(admin.page.locator('.toggle-switch')).toHaveCount(0)
  await shot(admin.page, 'security-channels')
  expect(admin.api.sourcesOf(/^\/api\/v1\/settings\/auth$/)).toEqual([SOURCES.BACKEND])
  expect(admin.api.unexpected(ALLOWED)).toEqual([])
})
