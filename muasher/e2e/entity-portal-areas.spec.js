import { mkdirSync } from 'node:fs'
import { deflateSync } from 'node:zlib'
import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedPreferences, seedSignedIn } from './support/session'
import { forceMockFault } from './support/mockFaults'
import { guardMoyasar } from './support/moyasarGuard'
import { settleFonts } from './support/screenshots'

const SHOTS = 'e2e/__screenshots__/37b'
const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  mobile: { width: 390, height: 844 },
}
const NAMAA = { id: 7601, roles: ['entity'], entityId: 6001, name: 'نورة بنت فهد العتيبي' }
const ALBIRR = {
  id: 5001,
  roles: ['entity'],
  entityId: 5001,
  name: { ar: 'أحمد بن سالم القحطاني', en: 'Ahmed Salem Al-Qahtani' },
}
const OPEN_THREAD = '/messages/7c2f4e1a-52b0-4c8e-9a31-0000000000a1'
const CLOSED_THREAD = '/messages/7c2f4e1a-52b0-4c8e-9a31-0000000000a2'
const VALID_CODE = '9F3K-MQ2T-7XHD-M4PA-Z81C-QW5N-RT6B'
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'

mkdirSync(SHOTS, { recursive: true })

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function signIn(page, testInfo, user = NAMAA, theme = 'light') {
  return seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar', theme })
}

async function open(page, path, ready) {
  await page.goto(path)
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await expect(page.locator(ready).first()).toBeVisible({ timeout: 10000 })
}

async function capture(page, name) {
  await settleFonts(page)
  await page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: true })
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

function crc32(buffer) {
  let crc = 0xffffffff
  for (const byte of buffer) {
    crc ^= byte
    for (let bit = 0; bit < 8; bit += 1) crc = (crc >>> 1) ^ (0xedb88320 & -(crc & 1))
  }
  return (crc ^ 0xffffffff) >>> 0
}

function chunk(type, data) {
  const length = Buffer.alloc(4)
  length.writeUInt32BE(data.length)
  const body = Buffer.concat([Buffer.from(type, 'ascii'), data])
  const crc = Buffer.alloc(4)
  crc.writeUInt32BE(crc32(body))
  return Buffer.concat([length, body, crc])
}

function squarePng(side) {
  const header = Buffer.alloc(13)
  header.writeUInt32BE(side, 0)
  header.writeUInt32BE(side, 4)
  header.set([8, 2, 0, 0, 0], 8)
  const row = Buffer.concat([Buffer.from([0]), Buffer.alloc(side * 3, 0x2a)])
  const pixels = Buffer.concat(Array.from({ length: side }, () => row))
  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', header),
    chunk('IDAT', deflateSync(pixels)),
    chunk('IEND', Buffer.alloc(0)),
  ])
}

test.describe('assessments area', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await signIn(page, testInfo)
  })

  test('shows the current, history and results tabs with the server counts', async ({ page }) => {
    await open(page, '/assessment/history', '.current-assessment')
    await expect(page.locator('[role="tab"]')).toHaveCount(3)
    await expect(page.locator('[role="tab"][aria-selected="true"]')).toHaveAttribute(
      'id',
      'assessments-panel-tab-current',
    )
    await expect(page.locator('.current-assessment', { hasText: 'ACC-2026-000004' })).toBeVisible()
    await expect(page.locator('.current-assessment [role="progressbar"]').first()).toBeVisible()
    await page.locator('[role="tab"]').nth(1).click()
    await expect(page).toHaveURL(/tab=history/)
    await expect(page.locator('.history-item').first()).toBeVisible()
    await page.locator('[role="tab"]').nth(2).click()
    await expect(page.locator('.assessment-result').first()).toBeVisible()
    await expect(page.locator('.assessment-result__percent').first()).toContainText('%')
  })

  test('opens the accredited form as a task screen with stations, guide and step navigation', async ({
    page,
  }) => {
    await open(page, '/assessment/history', '.current-assessment')
    await page
      .locator('.current-assessment', { hasText: 'ACC-2026-000004' })
      .locator('.btn--primary')
      .click()
    await expect(page).toHaveURL(/\/assessment\/accredited\/form\/9101$/)
    await expect(page.locator('[data-testid="attention-strip"]')).toHaveCount(0)
    await expect(page.locator('.portal-breadcrumb')).toContainText('ACC-2026-000004')
    await expect(page.locator('.request-card [role="progressbar"]')).toBeVisible()
    await expect(page.locator('.station-row__button')).toHaveCount(9)
    await expect(page.locator('.guide-aside__step').first()).toBeVisible()
    await page.locator('.step-nav__button--next').click()
    await expect(page.locator('.station-row__button').nth(1)).toHaveAttribute(
      'aria-current',
      'step',
    )
    await page.locator('.guide-aside__card .btn--secondary').click()
    await expect(page.locator('.modal__panel select[name="enquiryType"]')).toHaveValue(
      'accredited_assessment',
    )
    await page.keyboard.press('Escape')
    await page.locator('[role="tab"]').nth(1).click()
    await expect(page).toHaveURL(/\/assessment\/history\?tab=history$/)
  })
})

test.describe('certificates and public verification', () => {
  test.beforeEach(async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
  })

  test('groups the certificates, downloads the PDF and opens the public verification page', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo)
    await open(page, '/certificates', '.certificate-card')
    const counts = page.locator('[role="tab"] .area-tabs__count')
    await expect(counts).toHaveText(['1', '1', '1'])
    await expect(page.locator('.certificate-card')).toHaveCount(1)
    const download = page.waitForEvent('download')
    await page.locator('.certificate-card__actions .btn').click()
    expect((await download).suggestedFilename()).toBe('CRT-2026-000003.pdf')
    await page.locator('[role="tab"]').nth(1).click()
    await expect(page.locator('.issuing-certificate')).toContainText('ACC-2026-000004')
    await page.locator('[role="tab"]').nth(2).click()
    await expect(page.locator('.certificate-card__notice--muted')).toBeVisible()
    await page.locator('[role="tab"]').first().click()
    await page.locator(`.certificate-card a[href="/verify/${VALID_CODE}"]`).click()
    await expect(page.locator('.verification-result--valid')).toBeVisible()
  })

  test('verifies a typed code as a guest and reports an unknown code', async ({
    page,
  }, testInfo) => {
    await seedPreferences(page, { locale: isEn(testInfo) ? 'en' : 'ar' })
    await open(page, '/verify', '.verify-page__form')
    await page.locator('.verify-page__form input').fill(' b4ne-2wq8-hc7t-kx3m-5rza-9pdv-1gfy ')
    await page.locator('.verify-page__form .btn').click()
    await expect(page).toHaveURL(/\/verify\/B4NE-2WQ8-HC7T-KX3M-5RZA-9PDV-1GFY$/)
    await expect(page.locator('.verification-result--superseded')).toBeVisible()
    await page.locator('.verify-page__form input').fill('AAAA-BBBB-CCCC')
    await page.locator('.verify-page__form .btn').click()
    await expect(page.locator('.verification-result--not-found')).toBeVisible()
  })

  test('shows the feedback report of an accredited result and exports it', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo)
    await open(page, '/reports/feedback/9104', '.feedback-criterion')
    await expect(page.locator('.feedback-report__sub').first()).toBeVisible()
    const download = page.waitForEvent('download')
    await page.locator('.feedback-report__export .btn').click()
    expect((await download).suggestedFilename()).toMatch(/^feedback-ACC-2026-000003-(ar|en)\.pdf$/)
  })
})

test.describe('messages area', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await signIn(page, testInfo)
  })

  test('opens a thread, marks the reply read and sends a follow-up', async ({ page }) => {
    await open(page, '/messages', '.message-item')
    await expect(page.locator('[role="tab"] .area-tabs__count')).toHaveText(['2', '1'])
    await expect(page.locator('.message-item.is-unread')).toHaveCount(1)
    await page.locator('.message-item.is-unread a').click()
    await expect(page).toHaveURL(new RegExp(`${OPEN_THREAD}$`))
    await expect(page.locator('.message-bubble')).toHaveCount(2)
    await expect(page.locator('.message-item.is-unread')).toHaveCount(0)
    await expect(page.locator('.portal-nav__badge')).toHaveCount(0)
    await page.locator('.message-thread__composer textarea').fill('شكراً لكم، سنرفع الملفين معاً.')
    await page.locator('.message-thread__composer .btn--primary').click()
    await expect(page.locator('.message-bubble')).toHaveCount(3)
  })

  test('shows a closed thread read-only and sends a new message from the dialog', async ({
    page,
  }) => {
    await open(page, CLOSED_THREAD, '.message-thread__closed')
    await expect(page.locator('.message-thread textarea')).toHaveCount(0)
    await page.locator('.message-thread__closed .btn').click()
    const dialog = page.locator('.modal__panel')
    await dialog.locator('select[name="enquiryType"]').selectOption('privacy_request')
    await dialog
      .locator('textarea[name="message"]')
      .fill('نرجو تزويدنا بنسخة من البيانات المحفوظة لحساب الجهة.')
    await dialog.locator('.checkbox').first().click()
    await expect(dialog.locator('.legal-consent a')).toHaveAttribute('href', '/privacy')
    await dialog.locator('.modal__footer .btn--primary').click()
    await expect(page).toHaveURL(/\/messages\/[0-9a-f-]{36}$/)
    await expect(page.locator('.message-thread__title')).toBeVisible()
  })

  test('uses the reply bar as a task screen on mobile', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.mobile)
    await open(page, OPEN_THREAD, '.message-thread')
    await expect(page.locator('.mobile-tab-bar')).toHaveCount(0)
    await expect(page.locator('.mobile-action-bar textarea')).toBeVisible()
    await expect(page.locator('.messages-page__list')).toBeHidden()
    await noHorizontalOverflow(page)
    await page.locator('.message-thread__back').click()
    await expect(page).toHaveURL(/\/messages$/)
    await expect(page.locator('.mobile-tab-bar')).toBeVisible()
  })
})

test.describe('payments area', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await signIn(page, testInfo, ALBIRR)
  })

  test('lists the payment tabs, the stats of the unfiltered call and downloads a receipt', async ({
    page,
  }) => {
    await open(page, '/payments', '.my-payments__table')
    await expect(page.locator('[role="tab"]')).toHaveCount(4)
    await expect(page.locator('.payment-stats__tile')).toHaveCount(4)
    await page.locator('[role="tab"]').nth(2).click()
    await expect(page).toHaveURL(/tab=paid/)
    const row = page.locator('.my-payments__row', { hasText: 'RCP-2026-000001' })
    await expect(row).toContainText('ACC-2026-000001')
    const download = page.waitForEvent('download')
    await row.locator('.payment-action').click()
    expect((await download).suggestedFilename()).toBe('RCP-2026-000001.pdf')
  })

  test('completes an open order on its task screen', async ({ page }) => {
    const leaks = await guardMoyasar(page)
    await page.goto('/assessment/accredited/pay')
    await page.locator('.checkout__start .btn--primary').click()
    await expect(page.locator('[data-fake-moyasar="form"]')).toBeVisible()
    await open(page, '/payments?tab=awaiting', '.pending-order')
    await page.locator('.pending-order .btn--primary').click()
    await expect(page).toHaveURL(/\/payments\/[0-9a-f-]{36}\/complete$/)
    await expect(page.locator('[data-fake-moyasar="form"]')).toBeVisible()
    await expect(page.locator('.payment-complete__existing')).toBeVisible()
    await expect(page.locator('[data-testid="attention-strip"]')).toHaveCount(0)
    expect(leaks).toEqual([])
  })
})

test.describe('organization profile', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await signIn(page, testInfo)
  })

  test('saves the basic tab with the licence and the founding date', async ({ page }) => {
    await open(page, '/entity/profile', '.profile-form')
    await expect(page.locator('.profile-completeness__value')).toHaveText('25%')
    await page.locator('input[name="unifiedNumber"]').fill('7000006001')
    await page.locator('input[name="licenseNumber"]').fill('000123')
    await page.locator('input[name="foundedOn"]').fill('2012-03-01')
    await expect(page.locator('input[name="foundingYear"]')).toHaveValue('2012')
    await page.locator('.portal-area__actions .btn--primary').click()
    await expect(page.locator('.toast').first()).toBeVisible()
    await expect(page.locator('.profile-completeness__value')).toHaveText('62%')
  })

  test('guards unsaved changes when switching tabs and refuses an http website', async ({
    page,
  }) => {
    await open(page, '/entity/profile?tab=contact', '.official-email')
    await page.locator('input[name="websiteUrl"]').fill('http://namaa.example.sa')
    await page.locator('[role="tab"]').first().click()
    await expect(page.locator('.modal__panel')).toBeVisible()
    await page.locator('.modal__panel .btn--secondary').click()
    await expect(page).toHaveURL(/tab=contact/)
    await page.locator('.portal-area__actions .btn--primary').click()
    await expect(page.locator('.field__error').first()).toBeVisible()
  })

  test('uploads and removes the organization logo', async ({ page }) => {
    await open(page, '/entity/profile', '.entity-logo')
    await page
      .locator('.entity-logo__input')
      .setInputFiles({ name: 'namaa-logo.png', mimeType: 'image/png', buffer: squarePng(240) })
    await expect(page.locator('.entity-logo__image')).toBeVisible()
    await expect(page.locator('.entity-logo__meta .scan-status')).toBeVisible()
    await page.locator('.entity-logo__actions .btn--ghost').click()
    await page.locator('.modal .btn--danger').click()
    await expect(page.locator('.entity-logo__image')).toHaveCount(0)
    await page
      .locator('.entity-logo__input')
      .setInputFiles({ name: 'tiny.png', mimeType: 'image/png', buffer: squarePng(120) })
    await expect(page.locator('.entity-logo__error')).toBeVisible()
  })
})

test.describe('legal pages', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await seedPreferences(page, { locale: isEn(testInfo) ? 'en' : 'ar' })
  })

  test('links the published texts from the footer and renders them as blocks', async ({ page }) => {
    await open(page, '/privacy', '.legal-page__title')
    await expect(page.locator('.legal-page__meta')).toContainText('2026-11-draft')
    await expect(page.locator('.rich-text__table')).toBeVisible()
    await expect(page.locator('.rich-text__list--ordered li')).toHaveCount(3)
    await page.locator('.footer__legal a[href="/accessibility"]').click()
    await expect(page.locator('.legal-page__section-title')).toBeVisible()
    await page.locator('.footer__legal a[href="/terms"]').click()
    await expect(page).toHaveURL(/\/terms$/)
  })

  test('keeps the coming soon badge when a text is not published', async ({ page }) => {
    await forceMockFault(page, {
      method: 'GET',
      path: '/api/v1/content/legal/terms',
      status: 404,
      code: 'legal.not_published',
    })
    await open(page, '/privacy', '.legal-page__title')
    const terms = page.locator('.footer__legal .footer__soon').first()
    await expect(terms.locator('.footer__soon-tag')).toBeVisible()
    await expect(page.locator('.footer__legal a[href="/terms"]')).toHaveCount(0)
    await page.goto('/terms')
    await expect(page.locator('.legal-page .empty-state')).toBeVisible()
  })

  test('links the policies from the registration and the home contact consent', async ({
    page,
  }) => {
    await open(page, '/auth/register', '.legal-consent')
    await expect(page.locator('.legal-consent a')).toHaveCount(2)
    await open(page, '/', '.contact-form__form')
    await expect(page.locator('.contact-form .legal-consent a')).toHaveAttribute('href', '/privacy')
    await expect(
      page.locator('.contact-form select[name="enquiryType"] option[value="privacy_request"]'),
    ).toHaveCount(1)
  })
})

test.describe('portal areas screenshots', () => {
  const PAGES = [
    { name: 'assessments', path: '/assessment/history', ready: '.current-assessment' },
    { name: 'accredited-form', path: '/assessment/accredited/form/9101', ready: '.station-row' },
    { name: 'certificates', path: '/certificates', ready: '.certificate-card' },
    { name: 'messages', path: OPEN_THREAD, ready: '.message-bubble' },
    {
      name: 'messages-closed',
      path: `${CLOSED_THREAD}?tab=closed`,
      ready: '.message-thread__closed',
    },
    { name: 'payments', path: '/payments', ready: '.payment-stats__tile' },
    { name: 'profile', path: '/entity/profile', ready: '.profile-completeness' },
    { name: 'feedback-report', path: '/reports/feedback/9104', ready: '.feedback-criterion' },
    { name: 'verify', path: `/verify/${VALID_CODE}`, ready: '.verification-result' },
    { name: 'privacy', path: '/privacy', ready: '.rich-text__table' },
  ]

  for (const theme of ['light', 'dark']) {
    for (const [device, viewport] of Object.entries(VIEWPORTS)) {
      for (const entry of PAGES) {
        test(`captures ${entry.name} — ${theme} ${device}`, async ({ page }, testInfo) => {
          await page.emulateMedia({ reducedMotion: 'reduce' })
          await page.setViewportSize(viewport)
          await signIn(page, testInfo, NAMAA, theme)
          await open(page, entry.path, entry.ready)
          await noHorizontalOverflow(page)
          await expect(
            page.locator('.mobile-tab-bar, .portal-top-bar, .public-navbar').first(),
          ).toBeAttached()
          await capture(page, `${entry.name}-${theme}-${device}-${suffixOf(testInfo)}`)
        })
      }

      test(`captures the payment completion — ${theme} ${device}`, async ({ page }, testInfo) => {
        const suffix = `${theme}-${device}-${suffixOf(testInfo)}`
        await page.emulateMedia({ reducedMotion: 'reduce' })
        await page.setViewportSize(viewport)
        await guardMoyasar(page)
        await signIn(page, testInfo, ALBIRR, theme)
        await page.goto('/assessment/accredited/pay')
        await page.locator('.checkout__start .btn--primary').click()
        await expect(page.locator('[data-fake-moyasar="form"]')).toBeVisible()
        await open(page, '/payments?tab=awaiting', '.pending-order')
        await noHorizontalOverflow(page)
        await capture(page, `payments-pending-${suffix}`)
        await page.locator('.pending-order .btn--primary').click()
        await expect(page.locator('[data-fake-moyasar="form"]')).toBeVisible()
        await noHorizontalOverflow(page)
        await capture(page, `payment-complete-${suffix}`)
      })
    }
  }
})
