import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedPreferences, seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'

const SHOTS = 'e2e/__screenshots__/37c'
const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  mobile: { width: 390, height: 844 },
}
const USERS = {
  follower: { id: 8, roles: ['request_follower', 'filtering_officer'] },
  scopedFollower: { id: 28, roles: ['request_follower'] },
  assessor: { id: 10, roles: ['assessor'] },
  systemAdmin: { id: 1, roles: ['system_admin'] },
}
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'
const ROWS = '.table:not(.table--loading) tbody .table__row'
const STRONG = 'Namaa-Strong-2026!'

mkdirSync(SHOTS, { recursive: true })

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function text(testInfo, en, ar) {
  return isEn(testInfo) ? en : ar
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function signIn(page, testInfo, user, theme = 'light') {
  return seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar', theme })
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

async function capture(page, name) {
  await settleFonts(page)
  await page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: true })
}

test.describe('per-path queues', () => {
  test('the follower gets one sidebar entry and one queue per path with path badges', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.follower)
    await open(page, '/admin/requests', ROWS)
    const entries = page.locator('.sidebar__item--accredited, .sidebar__item--advanced')
    await expect(entries).toHaveCount(2)
    await expect(entries.first()).toHaveClass(/is-active/)
    await expect(entries.first().locator('.sidebar__count')).toHaveText('1')
    await expect(page.locator(`${ROWS} .path-badge--accredited`).first()).toBeVisible()
    await expect(page.locator(`${ROWS} .path-badge--advanced`)).toHaveCount(0)
    await entries.nth(1).click()
    await expect(page).toHaveURL(/\/admin\/requests\?path=advanced$/)
    await expect(page.locator(ROWS).first()).toContainText('ADV-')
    await expect(page.locator('.path-switch__link.is-active .path-badge--advanced')).toBeVisible()
    await expect(page.locator(`${ROWS} .path-badge--accredited`)).toHaveCount(0)
  })

  test('maps a dashboard status link to the status of each path and the overdue link to its filter', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.follower)
    await open(page, '/admin/requests?status=submitted,awaiting_review_assignment', ROWS)
    await expect(page.locator(ROWS)).toHaveCount(1)
    await expect(page.locator(ROWS).first()).toContainText('ACC-2026-000005')
    await page.locator('.path-switch__link').nth(1).click()
    await expect(page).toHaveURL(/path=advanced/)
    await expect(page.locator('.request-filters .dropdown-filter__trigger').first()).toContainText(
      text(testInfo, 'Awaiting assignment', 'بانتظار'),
    )
    await open(page, '/admin/requests?overdue=true', ROWS)
    await expect(page.locator(`${ROWS} .overdue-badge.badge--danger`).first()).toBeVisible()
    await expect(page.locator(`${ROWS} .overdue-badge:not(.badge--danger)`)).toHaveCount(0)
  })

  test('a follower limited to one path never sees the other path', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.scopedFollower)
    await open(page, '/admin/requests?path=advanced', ROWS)
    await expect(page.locator('.sidebar__item--advanced')).toHaveCount(0)
    await expect(page.locator('.path-switch')).toHaveCount(0)
    await expect(page.locator('.requests-page__title-row .path-badge--accredited')).toBeVisible()
    await expect(page.locator(`${ROWS} .path-badge--advanced`)).toHaveCount(0)
  })

  test('the assessor reviews per qualified path', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.assessor)
    await open(page, '/admin/requests/assigned?path=advanced', '.tabs__tab')
    await expect(page.locator('.sidebar__item--accredited, .sidebar__item--advanced')).toHaveCount(
      2,
    )
    await expect(page.locator('.sidebar__item--advanced')).toHaveClass(/is-active/)
    await page.getByRole('tab').nth(1).click()
    await expect(page.locator(ROWS).first()).toContainText('ADV-')
  })
})

test.describe('qualified assignment', () => {
  test('the assign dialog lists only the assessors qualified for the path of the request', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.follower)
    await open(page, '/admin/requests/9105', '.request-actions')
    await expect(page.locator('.request-detail__number .path-badge--accredited')).toBeVisible()
    await page.locator('.request-actions .btn--primary').click()
    const dialog = page.locator('.modal')
    await expect(dialog.locator('.assign-dialog__option').first()).toBeVisible()
    await expect(dialog.locator('.assign-dialog__path .path-badge--accredited')).toBeVisible()
    await expect(dialog.locator('.assign-dialog__option', { hasText: 'David Nguyen' })).toHaveCount(
      0,
    )
    await expect(dialog.locator('.assign-dialog__option', { hasText: 'عمر بن خالد' })).toHaveCount(
      0,
    )
  })
})

test.describe('users path access', () => {
  test('the System Administrator qualifies an assessor and limits a follower', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/settings/users', ROWS)
    await page.locator('.toolbar__search input').fill('David')
    const row = page.locator(ROWS).filter({ hasText: 'David Nguyen' })
    await expect(row).toHaveCount(1)
    await expect(row).toContainText(
      text(testInfo, 'Not qualified for any path', 'غير مؤهل لأي مسار'),
    )
    await row.locator('.dropdown-menu > button').click()
    await page
      .locator('[role="menuitem"]', {
        hasText: text(testInfo, 'Qualified paths', 'مسارات التأهيل'),
      })
      .click()
    await page.locator('.modal .checkbox__input').first().check()
    await page.locator('.modal button[type="submit"]').click()
    await expect(row.locator('.path-badge--accredited')).toBeVisible()

    await page.locator('.toolbar__search input').fill('Laura')
    const follower = page.locator(ROWS).filter({ hasText: 'Laura Bennett' })
    await expect(follower).toContainText(text(testInfo, 'All paths', 'كل المسارات'))
    await follower.locator('.dropdown-menu > button').click()
    await page
      .locator('[role="menuitem"]', {
        hasText: text(testInfo, 'Follow-up scope', 'نطاق المتابعة'),
      })
      .click()
    await page.locator('.modal .radio__input').nth(1).check()
    await page.locator('.modal button[type="submit"]').click()
    await expect(page.locator('.modal .field__error')).toBeVisible()
    await page.locator('.modal .checkbox__input').nth(1).check()
    await page.locator('.modal button[type="submit"]').click()
    await expect(follower.locator('.path-badge--advanced')).toBeVisible()
    await expect(follower.locator('.path-badge--accredited')).toHaveCount(0)
  })
})

test.describe('per-path and certificate settings', () => {
  test('each path keeps its own limits, price and entity texts', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/settings/assessment', 'input[name="reviewSlaDays"]')
    await expect(page.locator('input[name="price"]')).toHaveValue('1000.00')
    await page.locator('input[name="price"]').fill('1250')
    await page
      .locator('textarea[name="entityTexts.afterSubmission.ar"]')
      .fill('يبدأ المقيّم المراجعة بعد الإرسال.')
    await page.locator('textarea[name="entityTexts.afterSubmission.en"]').fill('')
    await page.locator('.path-settings .btn--primary').click()
    await expect(page.locator('.path-settings .field__error')).toHaveCount(1)
    await page
      .locator('textarea[name="entityTexts.afterSubmission.en"]')
      .fill('The assessor starts the review after submission.')
    await page.locator('.path-settings .btn--primary').click()
    await expect(page.locator('.toast--success').first()).toBeVisible()
    await expect(page.locator('input[name="price"]')).toHaveValue('1250.00')
    await page
      .getByRole('tab', {
        name: text(testInfo, 'Advanced assessment (Award)', 'التقييم المتقدم (الجائزة)'),
      })
      .click()
    await expect(page).toHaveURL(/tab=advanced/)
    await expect(page.locator('input[name="reviewSlaDays"]')).toBeVisible()
    await expect(page.locator('input[name="price"]')).toHaveCount(0)
    await page
      .getByRole('tab', { name: text(testInfo, 'General settings', 'إعدادات عامة') })
      .click()
    await expect(page.locator('input[name="generalUploadMaxFileMb"]')).toHaveValue('20')
    await expect(page.locator('input[name="uploadMaxFileMb"]')).toHaveCount(0)
  })

  test('the certificate thresholds must ascend and drive the resulting levels', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/settings/certificates', 'input[name="levelThresholds.bronze"]')
    await expect(page.locator('.level-ranges__item')).toHaveCount(5)
    await expect(page.locator('.certificate-editor')).toHaveCount(0)
    await page.locator('input[name="levelThresholds.gold"]').fill('65')
    await expect(page.locator('.level-ranges__empty')).toBeVisible()
    await page.locator('button[form="certificate-settings-form"]').click()
    await expect(page.locator('.field__error').first()).toContainText(
      text(
        testInfo,
        'higher than the threshold of the previous level',
        'أعلى من حد المستوى السابق',
      ),
    )
    await page.locator('input[name="levelThresholds.gold"]').fill('82.5')
    await page.locator('.toggle-switch').first().click()
    await page.locator('button[form="certificate-settings-form"]').click()
    await expect(page.locator('.toast--success').first()).toBeVisible()
    await expect(page.locator('input[name="levelThresholds.gold"]')).toHaveValue('82.50')
    await expect(page.locator('.level-ranges__item').nth(2)).toContainText('82.49')
    await expect(page.locator('.level-ranges__item').nth(3)).toContainText('82.50')
  })
})

test.describe('staff entity detail', () => {
  test('shows the registry details, the logo through the signed link and their history', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/entities/6003', '.entity-record__title')
    const details = page.locator('.entity-record__grid')
    await expect(details).toContainText('8806003')
    await expect(details).toContainText('2018-03-01')
    const website = page.locator('.entity-details__link')
    await expect(website).toHaveAttribute('href', 'https://www.alkhair-waqf.example.sa')
    await expect(website).toHaveAttribute('rel', 'noopener noreferrer')
    const logo = page.locator('.entity-logo-preview__image')
    await expect(logo).toBeVisible()
    await expect.poll(() => logo.evaluate((image) => image.naturalWidth)).toBeGreaterThan(0)
    await expect(page.locator('.profile-history tbody tr')).toHaveCount(2)
    await expect(page.locator('.profile-history')).toContainText(
      text(testInfo, 'Licence number', 'رقم الترخيص'),
    )
    await open(page, '/admin/entities', ROWS)
    await expect(page.locator('img[src*="/files/"]')).toHaveCount(0)
  })
})

test.describe('password recovery and invitations', () => {
  test('the sign-in page links the forgotten password and the request stays neutral', async ({
    page,
  }, testInfo) => {
    await seedPreferences(page, { locale: isEn(testInfo) ? 'en' : 'ar' })
    await open(page, '/auth/login', '.auth-form__forgot')
    await page.locator('.auth-form__forgot').click()
    await expect(page).toHaveURL(/\/auth\/forgot-password$/)
    await page.locator('[name="email"]').fill('nobody@example.sa')
    await page.locator('form button[type="submit"]').click()
    await expect(page.locator('.auth-status--success')).toContainText(
      text(testInfo, 'If this e-mail is registered', 'إن كان هذا البريد مسجلًا'),
    )
  })

  test('a reset link sets a new password and returns to sign in, an expired link offers a new one', async ({
    page,
  }, testInfo) => {
    await seedPreferences(page, { locale: isEn(testInfo) ? 'en' : 'ar' })
    await open(page, '/auth/reset-password/a1b2c3d4e5f6a7b8', '[name="email"]')
    await page.locator('[name="email"]').fill('abdullah.alqahtani@moashir.example.sa')
    await page.locator('[name="password"]').fill(STRONG)
    await page.locator('[name="passwordConfirmation"]').fill(STRONG)
    await page.locator('form button[type="submit"]').click()
    await expect(page).toHaveURL(/\/auth\/login\?notice=password-reset$/)
    await expect(page.locator('.auth-status--success')).toBeVisible()
    await open(page, '/auth/reset-password/expired-reset-link-2026', '[name="email"]')
    await page.locator('[name="email"]').fill('abdullah.alqahtani@moashir.example.sa')
    await page.locator('[name="password"]').fill(STRONG)
    await page.locator('[name="passwordConfirmation"]').fill(STRONG)
    await page.locator('form button[type="submit"]').click()
    await expect(page.locator('.auth-status--danger a')).toHaveAttribute(
      'href',
      '/auth/forgot-password',
    )
  })

  test('an invited staff member activates the account and goes on to two-factor enrolment', async ({
    page,
  }, testInfo) => {
    await seedPreferences(page, { locale: isEn(testInfo) ? 'en' : 'ar' })
    await open(page, '/auth/invitations/invite-expired-2026', '[name="password"]')
    await page.locator('[name="password"]').fill(STRONG)
    await page.locator('[name="passwordConfirmation"]').fill(STRONG)
    await page.locator('[name="termsAccepted"]').check()
    await page.locator('form button[type="submit"]').click()
    await expect(page.locator('.auth-status--danger')).toContainText(
      text(testInfo, 'The invitation has expired', 'انتهت صلاحية الدعوة'),
    )
    await open(page, '/auth/invitations/invite-sarah-whitfield-2026', '[name="password"]')
    await expect(page.locator('.legal-consent')).toBeVisible()
    await page.locator('[name="password"]').fill(STRONG)
    await page.locator('[name="passwordConfirmation"]').fill(STRONG)
    await page.locator('form button[type="submit"]').click()
    await expect(page.locator('.checkbox .field__error, .field__error').first()).toBeVisible()
    await page.locator('[name="termsAccepted"]').check()
    await page.locator('form button[type="submit"]').click()
    await expect(page).toHaveURL(/\/auth\/mfa/)
  })
})

test.describe('staff sidebar', () => {
  test('stays in view while the page scrolls and folds its groups with a remembered state', async ({
    page,
  }, testInfo) => {
    await page.setViewportSize(VIEWPORTS.desktop)
    await signIn(page, testInfo, USERS.follower)
    await open(page, '/admin/requests', ROWS)
    await page.mouse.wheel(0, 4000)
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(0)
    const box = await page.locator('.sidebar').boundingBox()
    expect(Math.abs(box.y)).toBeLessThanOrEqual(1)
    expect(Math.round(box.height)).toBe(VIEWPORTS.desktop.height)
    const requests = page.locator('#sidebar-group-assessment')
    await expect(requests).toHaveAttribute('aria-expanded', 'true')
    const overview = page.locator('#sidebar-group-overview')
    await expect(overview).toHaveAttribute('aria-expanded', 'false')
    await expect(page.locator('#sidebar-menu-overview')).toBeHidden()
    await overview.focus()
    await page.keyboard.press('Enter')
    await expect(overview).toHaveAttribute('aria-expanded', 'true')
    await expect(page.locator('#sidebar-menu-overview')).toBeVisible()
    await page.reload()
    await expect(page.locator('#sidebar-group-overview')).toHaveAttribute('aria-expanded', 'true')
  })
})

const CAPTURES = [
  { name: 'queue', user: USERS.follower, path: '/admin/requests', ready: ROWS },
  {
    name: 'queue-advanced',
    user: USERS.follower,
    path: '/admin/requests?path=advanced',
    ready: ROWS,
  },
  { name: 'assigned', user: USERS.assessor, path: '/admin/requests/assigned', ready: '.tabs__tab' },
  {
    name: 'request-detail',
    user: USERS.follower,
    path: '/admin/requests/9105',
    ready: '.request-actions',
  },
  { name: 'users', user: USERS.systemAdmin, path: '/admin/settings/users', ready: ROWS },
  {
    name: 'path-settings',
    user: USERS.systemAdmin,
    path: '/admin/settings/assessment',
    ready: 'input[name="reviewSlaDays"]',
  },
  {
    name: 'certificate-settings',
    user: USERS.systemAdmin,
    path: '/admin/settings/certificates',
    ready: '.level-ranges__item',
  },
  {
    name: 'entity-detail',
    user: USERS.systemAdmin,
    path: '/admin/entities/6003',
    ready: '.entity-logo-preview__image',
  },
  { name: 'forgot-password', path: '/auth/forgot-password', ready: '[name="email"]' },
  {
    name: 'reset-password',
    path: '/auth/reset-password/a1b2c3d4e5f6a7b8',
    ready: '[name="email"]',
  },
  {
    name: 'invitation',
    path: '/auth/invitations/invite-sarah-whitfield-2026',
    ready: '[name="password"]',
  },
]

function prepareShot(page, testInfo, shot, theme) {
  const locale = isEn(testInfo) ? 'en' : 'ar'
  return shot.user
    ? seedSignedIn(page, shot.user, { locale, theme })
    : seedPreferences(page, { locale, theme })
}

test.describe('staff paths screenshots', () => {
  for (const shot of CAPTURES) {
    for (const theme of ['light', 'dark']) {
      for (const [viewport, size] of Object.entries(VIEWPORTS)) {
        test(`${shot.name} ${theme} ${viewport}`, async ({ page }, testInfo) => {
          await page.setViewportSize(size)
          await prepareShot(page, testInfo, shot, theme)
          await open(page, shot.path, shot.ready)
          await expect(page.locator('html')).toHaveAttribute('data-theme', theme)
          await noHorizontalOverflow(page)
          await capture(page, `${shot.name}-${theme}-${viewport}-${suffixOf(testInfo)}`)
        })
      }
    }
  }

  test('qualifications dialog light desktop', async ({ page }, testInfo) => {
    await page.setViewportSize(VIEWPORTS.desktop)
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/settings/users', ROWS)
    await page.locator('.toolbar__search input').fill('David')
    const row = page.locator(ROWS).filter({ hasText: 'David Nguyen' })
    await row.locator('.dropdown-menu > button').click()
    await page
      .locator('[role="menuitem"]', {
        hasText: text(testInfo, 'Qualified paths', 'مسارات التأهيل'),
      })
      .click()
    await expect(page.locator('.path-access-form')).toBeVisible()
    await capture(page, `qualifications-dialog-light-desktop-${suffixOf(testInfo)}`)
  })

  for (const theme of ['light', 'dark']) {
    test(`sidebar scrolled ${theme} desktop`, async ({ page }, testInfo) => {
      await page.setViewportSize(VIEWPORTS.desktop)
      await signIn(page, testInfo, USERS.follower, theme)
      await open(page, '/admin/requests', ROWS)
      await page.mouse.wheel(0, 4000)
      await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(0)
      await settleFonts(page)
      await page.screenshot({
        path: `${SHOTS}/sidebar-scrolled-${theme}-desktop-${suffixOf(testInfo)}.png`,
      })
    })
  }

  test('assign dialog dark desktop', async ({ page }, testInfo) => {
    await page.setViewportSize(VIEWPORTS.desktop)
    await signIn(page, testInfo, USERS.follower, 'dark')
    await open(page, '/admin/requests/9105', '.request-actions')
    await page.locator('.request-actions .btn--primary').click()
    await expect(page.locator('.modal .assign-dialog__option').first()).toBeVisible()
    await capture(page, `assign-dialog-dark-desktop-${suffixOf(testInfo)}`)
  })
})
