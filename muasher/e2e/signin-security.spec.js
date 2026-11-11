import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedPreferences, seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'
import {
  DEV_OTP_CODE,
  fillSignIn,
  openSignIn,
  requestSignInCode,
  signInTab,
  submitSignIn,
  typeCode,
} from './support/signIn'
import { chooseNamed } from './support/dropdown'
import { AA_NON_TEXT, contrastOf } from './support/contrast'

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
const CODE_TARGETS = [
  { identifier: ALBIRR.email, targets: ['in•••@albirr-riyadh.example.sa'] },
  { identifier: ALBIRR.mobile, targets: [] },
  { identifier: ALBIRR.unifiedNumber, targets: [] },
]
const UNKNOWN_EMAIL = 'nobody@example.sa'
const WRONG_CODE = '000000'
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

async function signOut(page) {
  await page.locator('.portal-top-bar .account-menu__chip, .navbar__avatar').first().click()
  await page
    .locator('.account-menu__panel .portal-menu-entry--danger, [role="menuitem"].is-danger')
    .first()
    .click()
  await expect(page.locator('[name="identifier"]')).toBeVisible()
}

test.describe('entity sign-in: password or code', () => {
  test('the password tab signs an entity in with each identifier, with no code step', async ({
    page,
  }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo) })
    for (const { identifier } of CODE_TARGETS) {
      await openSignIn(page)
      await expect(signInTab(page, 'password')).toHaveAttribute('aria-selected', 'true')
      await submitSignIn(page, identifier)
      await expect(page.locator('.portal-top-bar')).toBeVisible()
      await expect(page).toHaveURL(/\/dashboard/)
      await page.context().clearCookies({ name: 'moashir_mock_session' })
    }
  })

  test('the code tab signs an entity in with each identifier and names only what was typed', async ({
    page,
  }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo) })
    for (const { identifier, targets } of CODE_TARGETS) {
      await openSignIn(page)
      await requestSignInCode(page, identifier)
      await expect(page).toHaveURL(/\/auth\/otp/)
      await expect(page.locator('.auth-otp__tile')).toHaveAttribute('data-channel', 'email')
      await expect(page.locator('.auth-otp__target')).toHaveText(targets)
      await expect(page.locator('.otp-input__box')).toHaveCount(6)
      await expect(page.locator('.auth-otp__password')).toHaveText(
        text(testInfo, 'Sign in with your password instead', 'الدخول بكلمة المرور بدلًا من ذلك'),
      )
      await typeCode(page, DEV_OTP_CODE)
      await expect(page.locator('.portal-top-bar')).toBeVisible()
      await page.context().clearCookies({ name: 'moashir_mock_session' })
    }
  })

  test('the tabs follow the keyboard, keep the identifier and mark the selected tab at 3:1', async ({
    page,
  }, testInfo) => {
    for (const theme of ['light', 'dark']) {
      await seedPreferences(page, { locale: localeOf(testInfo), theme })
      await openSignIn(page)
      await page.locator('[name="identifier"]').fill(ALBIRR.unifiedNumber)
      await signInTab(page, 'password').focus()
      await page.keyboard.press(isEn(testInfo) ? 'ArrowRight' : 'ArrowLeft')
      await expect(signInTab(page, 'code')).toHaveAttribute('aria-selected', 'true')
      await expect(signInTab(page, 'code')).toBeFocused()
      await expect(page.locator('[name="identifier"]')).toHaveValue(ALBIRR.unifiedNumber)
      await expect(page.locator('[name="password"]')).toHaveCount(0)
      const selected = '[role="tab"][aria-selected="true"]'
      const indicator = await contrastOf(page, selected, {
        property: 'borderTopColor',
        behind: true,
      })
      expect(indicator).toBeGreaterThanOrEqual(AA_NON_TEXT)
      await page.keyboard.press('Home')
      await expect(signInTab(page, 'password')).toBeFocused()
      await expect(page.locator('[name="password"]')).toBeVisible()
    }
  })

  test('five wrong codes end the challenge and a new code starts from the code tab', async ({
    page,
  }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await openSignIn(page)
    await requestSignInCode(page, UNKNOWN_EMAIL)
    await expect(page).toHaveURL(/\/auth\/otp/)
    for (let attempt = 1; attempt <= 4; attempt += 1) {
      await typeCode(page, WRONG_CODE)
      await expect(page.locator('.auth-otp__error')).toBeVisible()
      await expect(page.locator('.otp-input__box').first()).toHaveValue('')
    }
    await typeCode(page, WRONG_CODE)
    const ended = page.locator('.auth-failure[data-failure="attempts"]')
    await expect(ended).toContainText(text(testInfo, 'Too many attempts', 'تجاوزت عدد المحاولات'))
    await expect(page.locator('.otp-input__box')).toHaveCount(0)
    await expect(page.locator('.auth-otp__resend')).toHaveCount(0)
    await page.locator('.auth-otp__request-new').click()
    await expect(page).toHaveURL(/\/auth\/login\?method=code/)
    await expect(signInTab(page, 'code')).toHaveAttribute('aria-selected', 'true')
    await expect(page.locator('[name="identifier"]')).toHaveValue(UNKNOWN_EMAIL)
  })

  test('the password link keeps the identifier and a fourth request of one identifier waits', async ({
    page,
  }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await openSignIn(page)
    for (let request = 1; request <= 3; request += 1) {
      await requestSignInCode(page, ALBIRR.unifiedNumber)
      await expect(page).toHaveURL(/\/auth\/otp/)
      await page.locator('.auth-otp__password').click()
      await expect(signInTab(page, 'password')).toHaveAttribute('aria-selected', 'true')
      await expect(page.locator('[name="identifier"]')).toHaveValue(ALBIRR.unifiedNumber)
    }
    await requestSignInCode(page, ALBIRR.unifiedNumber)
    const throttled = page.locator('.auth-failure[data-failure="throttled"]')
    await expect(throttled).toContainText(text(testInfo, 'Too many requests.', 'طلبات كثيرة.'))
    await expect(throttled).toContainText(text(testInfo, '10 minutes', '10 دقائق'))
    await expect(page).toHaveURL(/\/auth\/login/)
  })

  test('the System Administrator allows SMS codes, then the verified mobile gets its code by SMS', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, USERS.systemAdmin, { locale: localeOf(testInfo) })
    await open(page, '/admin/settings/sign-in', '.sign-in-channels')
    const email = page.locator('input[name="codeChannelEmail"]')
    const sms = page.locator('input[name="codeChannelSms"]')
    await expect(email).toBeChecked()
    await expect(email).toBeDisabled()
    await expect(sms).not.toBeChecked()
    await expect(page.locator('.sign-in-channels__warning')).toBeVisible()
    await page.locator('label.checkbox:has([name="codeChannelSms"])').click()
    await expect(sms).toBeChecked()
    const saved = page.waitForResponse(
      (response) =>
        new URL(response.url()).pathname === '/api/v1/settings/auth' &&
        response.request().method() === 'PUT',
    )
    await page.locator('.settings-section button[type="submit"]').click()
    expect((await saved).status()).toBe(200)
    await expect(page.locator('.toast').first()).toBeVisible()
    await signOut(page)
    await signInTab(page, 'code').click()
    await expect(page.locator('[data-note="channel"]')).toContainText(
      text(testInfo, 'by SMS', 'برسالة نصية'),
    )
    await requestSignInCode(page, ALBIRR.mobile)
    await expect(page).toHaveURL(/\/auth\/otp/)
    await expect(page.locator('.auth-otp__tile')).toHaveAttribute('data-channel', 'sms')
    await expect(page.locator('.auth-otp__target')).toHaveText('05•••••01')
    await expect(page.locator('.auth-form__subtitle')).toContainText(
      text(testInfo, 'the mobile you entered', 'الجوال الذي أدخلته'),
    )
    await typeCode(page, DEV_OTP_CODE)
    await expect(page.locator('.portal-top-bar')).toBeVisible()
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
    await open(page, '/entity/profile', '[data-section="identity"]')
    const identity = page.locator('[data-section="identity"]')
    await expect(identity.locator('[data-field="unifiedNumber"]')).toContainText(
      text(testInfo, 'Not provided', 'غير مُدخل'),
    )
    await identity.locator('[data-field="unifiedNumber"] .profile-missing__add').click()
    await identity.locator('[name="unifiedNumber"]').fill('7000006001')
    await identity.locator('button[type="submit"]').click()
    await expect(identity.locator('.profile-section__status-text--success')).toBeVisible()
    await expect(identity.locator('[data-field="unifiedNumber"]')).toContainText('7000006001')
    const contact = page.locator('[data-section="contact"]')
    const card = contact.getByTestId('profile-phone')
    await expect(card.locator('.profile-field__action')).toHaveText(text(testInfo, 'Add', 'أضِف'))
    await card.locator('.profile-field__action').click()
    await contact.locator('[name="phone"]').fill('0500006001')
    await contact.locator('button[type="submit"]').click()
    await expect(card.locator('.badge--warning')).toBeVisible()
    await card.locator('.profile-field__action').click()
    await expect(card.locator('.otp-input__box')).toHaveCount(6)
    await typeCode(page, DEV_OTP_CODE)
    await expect(card.locator('.badge--success')).toBeVisible()
    await expect(card.locator('bdi[dir="ltr"]')).toHaveText('050 000 6001')
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
    name: 'login-code',
    path: '/auth/login?method=code',
    ready: '[name="identifier"]',
    settled: '[data-note="channel"]',
  },
  {
    name: 'otp-code',
    path: '/auth/login',
    ready: '[name="identifier"]',
    settled: '.otp-input__box',
    async prepare(page) {
      await requestSignInCode(page, ALBIRR.email)
    },
  },
  {
    name: 'otp-code-ended',
    path: '/auth/login',
    ready: '[name="identifier"]',
    settled: '.auth-otp__request-new',
    async prepare(page) {
      await requestSignInCode(page, UNKNOWN_EMAIL)
      for (let attempt = 0; attempt < 5; attempt += 1) {
        await typeCode(page, WRONG_CODE)
        await page.locator('.auth-otp__error, .auth-otp__request-new').first().waitFor()
      }
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
    ready: '.sign-in-channels',
  },
  {
    name: 'security-settings-sms',
    user: USERS.systemAdmin,
    path: '/admin/settings/sign-in',
    ready: '.sign-in-channels',
    settled: 'input[name="codeChannelSms"]:checked',
    async prepare(page) {
      await page.locator('label.checkbox:has([name="codeChannelSms"])').click()
      await page.waitForFunction(() => {
        const fillOf = (name) =>
          getComputedStyle(document.querySelector(`input[name="${name}"]`)).backgroundColor
        return fillOf('codeChannelSms') === fillOf('codeChannelEmail')
      })
    },
  },
  { name: 'register', path: '/auth/register', ready: '[name="unifiedNumber"]' },
  {
    name: 'profile-basic',
    user: USERS.namaa,
    path: '/entity/profile',
    ready: '[data-section="identity"]',
    settled: '[data-section="identity"] [name="unifiedNumber"]',
    async prepare(page) {
      await page.locator('[data-section="identity"] .profile-section__edit').click()
    },
  },
  {
    name: 'profile-phone',
    user: USERS.albirr,
    path: '/entity/profile',
    ready: '[data-testid="profile-phone"]',
  },
  {
    name: 'profile-phone-code',
    user: USERS.namaa,
    path: '/entity/profile',
    ready: '[data-testid="profile-phone"]',
    settled: '[data-testid="phone-otp"]',
    async prepare(page) {
      const contact = page.locator('[data-section="contact"]')
      await contact.locator('.profile-section__edit').click()
      await contact.locator('[name="phone"]').fill('0500006001')
      await contact.locator('button[type="submit"]').click()
      await contact.locator('[data-testid="profile-phone"] .badge--warning').waitFor()
      await contact.locator('[data-testid="profile-phone"] .profile-field__action').click()
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
