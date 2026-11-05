import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedPreferences, seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'
import { DEV_OTP_CODE, fillSignIn, openSignIn, submitSignIn, typeCode } from './support/signIn'
import { chooseNamed } from './support/dropdown'

const SHOTS = 'e2e/__screenshots__/signin'
const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  mobile: { width: 390, height: 844 },
}
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'
const USERS = {
  systemAdmin: { id: 1, roles: ['system_admin'] },
  albirr: { id: 5001, roles: ['entity'], entityId: 5001 },
  namaa: { id: 7601, roles: ['entity'], entityId: 6001, name: 'نورة بنت فهد العتيبي' },
}
const ALBIRR = {
  email: 'info@albirr-riyadh.example.sa',
  mobile: '0500005001',
  unifiedNumber: '7100005001',
}
const IDENTIFIERS = [
  { identifier: ALBIRR.email, targets: ['in***@albirr-riyadh.example.sa'] },
  { identifier: ALBIRR.mobile, targets: [] },
  { identifier: ALBIRR.unifiedNumber, targets: [] },
]
const WRONG_PASSWORD = 'Not-The-Password-2026'
const MOYASAR_FORM_SCRIPT = 'https://cdn.moyasar.com/mpf/1.15.0/moyasar.js'

mkdirSync(SHOTS, { recursive: true })

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function text(testInfo, en, ar) {
  return isEn(testInfo) ? en : ar
}

function localeOf(testInfo) {
  return isEn(testInfo) ? 'en' : 'ar'
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

async function open(page, path, ready) {
  await page.goto(path)
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await expect(page.locator(ready).first()).toBeVisible({ timeout: 10000 })
}

async function noHorizontalOverflow(page) {
  await expect
    .poll(() =>
      page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
      ),
    )
    .toBeLessThanOrEqual(1)
}

test.describe('simplified sign-in', () => {
  test('one form signs an entity in with each identifier after the e-mail code', async ({
    page,
  }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo) })
    for (const { identifier, targets } of IDENTIFIERS) {
      await openSignIn(page)
      await expect(page.locator('[role="tablist"]')).toHaveCount(0)
      await submitSignIn(page, identifier)
      await expect(page).toHaveURL(/\/auth\/otp/)
      await expect(page.locator('.auth-otp__target')).toHaveText(targets)
      await expect(page.locator('.otp-input__box')).toHaveCount(6)
      await expect(page.locator('.auth-otp__resend')).toBeVisible()
      await typeCode(page, DEV_OTP_CODE)
      await expect(page.locator('.portal-top-bar')).toBeVisible()
      await page.context().clearCookies({ name: 'moashir_mock_session' })
    }
  })

  test('wrong credentials get one generic message and five failures lock the account', async ({
    page,
  }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await openSignIn(page)
    const failure = page.locator('.auth-failure')
    for (let attempt = 1; attempt <= 4; attempt += 1) {
      await submitSignIn(page, ALBIRR.unifiedNumber, WRONG_PASSWORD)
      await expect(failure).toHaveAttribute('data-failure', 'credentials')
      await expect(page.locator('[name="password"]')).toHaveValue('')
    }
    await submitSignIn(page, 'nobody@example.sa', WRONG_PASSWORD)
    await expect(failure).toHaveAttribute('data-failure', 'credentials')
    await submitSignIn(page, ALBIRR.unifiedNumber, WRONG_PASSWORD)
    await expect(failure).toHaveAttribute('data-failure', 'locked')
    await expect(failure).toContainText(text(testInfo, '15 minutes', '15 دقيقة'))
    await fillSignIn(page, '12345')
    await page.locator('form button[type="submit"]').click()
    await expect(page.locator('.field__error').first()).toContainText(
      text(testInfo, '10 digits starting with 7', '10 أرقام تبدأ بالرقم 7'),
    )
  })

  test('the System Administrator turns the entity code off, then entities sign in after the password', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, USERS.systemAdmin, { locale: localeOf(testInfo) })
    await open(page, '/admin/settings/sign-in', '.toggle-switch')
    const toggle = page.locator('input[name="entitySecondFactorEnabled"]')
    await expect(toggle).toBeChecked()
    await expect(page.locator('.second-factor-risk')).toHaveCount(0)
    await page.locator('.toggle-switch__track').click()
    await expect(page.locator('.second-factor-risk')).toBeVisible()
    await page.locator('.settings-section button[type="submit"]').click()
    const dialog = page.locator('.modal__panel')
    await expect(dialog).toContainText(text(testInfo, 'password alone', 'بكلمة المرور وحدها'))
    await dialog.locator('.btn--danger').click()
    await expect(page.locator('.toast').first()).toBeVisible()
    await page.locator('.navbar__avatar').click()
    await page.locator('[role="menuitem"].is-danger').click()
    await expect(page.locator('[name="identifier"]')).toBeVisible()
    await submitSignIn(page, ALBIRR.unifiedNumber)
    await expect(page.locator('.portal-top-bar')).toBeVisible()
  })

  test('the language switch and the bells draw no misleading stand-in', async ({
    page,
  }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await openSignIn(page)
    const switcher = page.getByRole('button', { name: text(testInfo, 'العربية', 'English') })
    await expect(switcher).toBeVisible()
    await expect(switcher.locator('svg')).toHaveCount(0)
    await seedSignedIn(page, USERS.namaa, { locale: localeOf(testInfo) })
    await open(page, '/dashboard', '.portal-top-bar')
    await expect(page.locator('.portal-bell svg')).toHaveAttribute('data-icon', 'bell')
  })
})

test.describe('unified national number and mobile verification', () => {
  test('registration requires the number, keeps digits only and shows the duplicate answer', async ({
    page,
  }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await open(page, '/auth/register', '[name="unifiedNumber"]')
    const field = page.locator('[name="unifiedNumber"]')
    await expect(field).toHaveAttribute('inputmode', 'numeric')
    await field.pressSequentially('71a00-005001')
    await expect(field).toHaveValue('7100005001')
    await page.locator('[name="officialEmail"]').fill('info@alwafa-charity.example.sa')
    await page.locator('[name="password"]').fill('Wafa-Charity-Account-2026')
    await page.locator('[name="passwordConfirmation"]').fill('Wafa-Charity-Account-2026')
    await page.locator('[name="name.ar"]').fill('جمعية الوفاء الخيرية')
    await page.locator('[name="authorizedPersonName"]').fill('سلمان بن فهد الحربي')
    await chooseNamed(page, 'entityType', 'civil_association')
    await chooseNamed(page, 'developmentSector', 'social')
    await chooseNamed(page, 'orgSize', 'medium')
    await page.locator('[name="foundingYear"]').fill('2016')
    await chooseNamed(page, 'regionId', '2')
    await chooseNamed(page, 'cityId', '12')
    await page.locator('label.checkbox:has([name="termsAccepted"])').click()
    await page.locator('form button[type="submit"]').click()
    await expect(page.locator('.field:has([name="unifiedNumber"]) .field__error')).toContainText(
      text(testInfo, 'already registered for another entity', 'مسجّل لجهة أخرى'),
    )
  })

  test('the entity profile edits the number and verifies the mobile by SMS', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, USERS.namaa, { locale: localeOf(testInfo) })
    await open(page, '/entity/profile', '[name="unifiedNumber"]')
    await expect(page.locator('.profile-completeness__item').nth(5)).toContainText(
      text(testInfo, 'Unified national number', 'الرقم الوطني الموحد'),
    )
    await page.locator('[name="unifiedNumber"]').fill('7000006001')
    await page.locator('.portal-area__actions .btn--primary').click()
    await expect(page.locator('.toast--success')).toBeVisible()
    await expect(page.locator('.profile-form__dirty')).toHaveCount(0)
    await page.locator('#profile-panel-tab-contact').click()
    const card = page.locator('[data-testid="phone-verification"]')
    await expect(card).toContainText(text(testInfo, 'Add a mobile number', 'أضف رقم جوال'))
    await page.locator('[name="phone"]').fill('0500006001')
    await expect(card.locator('button')).toHaveCount(0)
    await page.locator('.portal-area__actions .btn--primary').click()
    await expect(page.locator('.profile-form__dirty')).toHaveCount(0)
    await expect(card.locator('.badge--warning')).toBeVisible()
    await card.locator('button').click()
    await expect(card.locator('.otp-input__box')).toHaveCount(6)
    await typeCode(page, DEV_OTP_CODE)
    await expect(card.locator('.badge--success')).toBeVisible()
    await expect(card.locator('.phone-verification__number')).toHaveAttribute('dir', 'ltr')
  })

  test('the staff entity detail shows the number as an isolated value', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, USERS.systemAdmin, { locale: localeOf(testInfo) })
    await open(page, '/admin/entities/5001', '.entity-details')
    const value = page.locator('.entity-details bdi[dir="ltr"]', { hasText: ALBIRR.unifiedNumber })
    await expect(value).toBeVisible()
  })
})

test('the payment form loads the script the checkout names, without SRI from the CDN', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, USERS.albirr, { locale: localeOf(testInfo) })
  const scripts = []
  page.on('request', (request) => {
    if (request.url().startsWith('https://cdn.moyasar.com/')) scripts.push(request.url())
  })
  await page.goto('/assessment/accredited/pay')
  await page.locator('.checkout__start .btn--primary').click()
  await expect(page.locator('[data-fake-moyasar="form"]')).toBeVisible({ timeout: 15000 })
  const loaded = page.locator('script[data-moyasar-form]')
  await expect(loaded).toHaveAttribute('src', MOYASAR_FORM_SCRIPT)
  expect(await loaded.getAttribute('integrity')).toBeNull()
  await expect(page.locator('link[data-moyasar-form]')).toHaveAttribute(
    'href',
    MOYASAR_FORM_SCRIPT.replace('.js', '.css'),
  )
  expect(scripts.some((url) => url.includes('/mpf/2.3.0/'))).toBe(false)
})

const NOTHING_TO_PREPARE = async () => {}

const CAPTURES = [
  { name: 'login', path: '/auth/login', ready: '[name="identifier"]' },
  {
    name: 'otp',
    path: '/auth/login',
    ready: '[name="identifier"]',
    settled: '.otp-input__box',
    async prepare(page) {
      await submitSignIn(page, ALBIRR.email)
    },
  },
  {
    name: 'login-locked',
    path: '/auth/login',
    ready: '[name="identifier"]',
    settled: '.auth-failure[data-failure="locked"]',
    async prepare(page) {
      for (let attempt = 0; attempt < 5; attempt += 1) {
        await submitSignIn(page, ALBIRR.unifiedNumber, WRONG_PASSWORD)
        await page.locator('.auth-failure').waitFor()
      }
    },
  },
  {
    name: 'security-settings',
    user: USERS.systemAdmin,
    path: '/admin/settings/sign-in',
    ready: '.toggle-switch',
  },
  {
    name: 'security-settings-off',
    user: USERS.systemAdmin,
    path: '/admin/settings/sign-in',
    ready: '.toggle-switch',
    settled: '.second-factor-risk',
    async prepare(page) {
      await page.locator('.toggle-switch__track').click()
    },
  },
  { name: 'register', path: '/auth/register', ready: '[name="unifiedNumber"]' },
  {
    name: 'profile-basic',
    user: USERS.namaa,
    path: '/entity/profile',
    ready: '[name="unifiedNumber"]',
  },
  {
    name: 'profile-phone',
    user: USERS.albirr,
    path: '/entity/profile?tab=contact',
    ready: '[data-testid="phone-verification"]',
  },
  {
    name: 'profile-phone-code',
    user: USERS.namaa,
    path: '/entity/profile?tab=contact',
    ready: '[data-testid="phone-verification"]',
    settled: '.phone-verification__form',
    async prepare(page) {
      await page.locator('[name="phone"]').fill('0500006001')
      await page
        .locator('.portal-area__actions .btn--primary:visible, .profile-form__submit:visible')
        .first()
        .click()
      await page.locator('[data-testid="phone-verification"] .badge--warning').waitFor()
      await page.locator('[data-testid="phone-verification"] button').click()
    },
  },
  {
    name: 'entity-detail',
    user: USERS.systemAdmin,
    path: '/admin/entities/5001',
    ready: '.entity-details',
  },
]

function prepareShot(page, testInfo, shot, theme) {
  const locale = localeOf(testInfo)
  return shot.user
    ? seedSignedIn(page, shot.user, { locale, theme })
    : seedPreferences(page, { locale, theme })
}

test.describe('sign-in and security screenshots', () => {
  for (const shot of CAPTURES) {
    for (const theme of ['light', 'dark']) {
      for (const [viewport, size] of Object.entries(VIEWPORTS)) {
        test(`${shot.name} ${theme} ${viewport}`, async ({ page }, testInfo) => {
          await page.setViewportSize(size)
          await prepareShot(page, testInfo, shot, theme)
          await open(page, shot.path, shot.ready)
          await (shot.prepare ?? NOTHING_TO_PREPARE)(page)
          await expect(page.locator(shot.settled ?? shot.ready).first()).toBeVisible()
          await expect(page.locator('html')).toHaveAttribute('data-theme', theme)
          await noHorizontalOverflow(page)
          await page.evaluate(() => window.scrollTo(0, 0))
          await settleFonts(page)
          await page.screenshot({
            path: `${SHOTS}/${shot.name}-${theme}-${viewport}-${suffixOf(testInfo)}.png`,
            fullPage: true,
          })
        })
      }
    }
  }
})
