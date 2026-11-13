import { mkdirSync } from 'node:fs'
import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'
import { closeSuccess, decisionAction } from './support/dialogs'

const SHOTS = '../agents/tasks/reports/Task-78'
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'
const MOBILE = { width: 390, height: 844 }
const PDF = Buffer.from('%PDF-1.4\n% Task-78 evidence\n%%EOF\n')

const USERS = {
  albirr: { id: 5001, roles: ['entity'], entityId: 5001 },
  namaa: { id: 6001, roles: ['entity'], entityId: 6001 },
  ruwad: { id: 6005, roles: ['entity'], entityId: 6005 },
}

mkdirSync(SHOTS, { recursive: true })

const isEn = (testInfo) => testInfo.project.name === 'chromium-en-ltr'
const say = (testInfo, en, ar) => (isEn(testInfo) ? en : ar)
const suffix = (testInfo) => (isEn(testInfo) ? 'en-ltr' : 'ar-rtl')

async function signIn(page, testInfo, user) {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar', theme: 'light' })
}

async function open(page, path, ready) {
  await page.goto(path)
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await expect(page.locator(ready).first()).toBeVisible({ timeout: 15000 })
}

async function shot(page, name, testInfo) {
  await settleFonts(page)
  await page.screenshot({ path: `${SHOTS}/${name}-${suffix(testInfo)}.png`, fullPage: true })
}

async function homeSettled(page) {
  await expect(
    page
      .locator(
        '.glance-notifications .notification-item, .glance-notifications .glance-card__empty',
      )
      .first(),
  ).toBeVisible()
}

async function resultVisible(page) {
  const tab = page.locator('[data-testid="request-tab-result"]')
  await expect(tab.locator('.accredited-result-summary__value')).toContainText('%')
  const spill = await tab.locator('.accredited-result-summary__gauge').evaluate((gauge) => {
    const ring = gauge.getBoundingClientRect()
    const value = gauge.firstElementChild.getBoundingClientRect()
    return Math.max(ring.left - value.left, value.right - ring.right)
  })
  expect(spill).toBeLessThanOrEqual(0)
  await expect(tab.locator('.skeleton')).toHaveCount(0)
}

async function noHorizontalOverflow(page) {
  await expect
    .poll(() =>
      page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
      ),
    )
    .toBeLessThanOrEqual(0)
}

async function navigateInApp(page, path) {
  await page.evaluate(
    (target) =>
      document.querySelector('#app').__vue_app__.config.globalProperties.$router.push(target),
    path,
  )
  await expect(page).toHaveURL(new RegExp(`${path}$`))
}

async function expectGutter(page, selector) {
  const gap = await page.evaluate((target) => {
    const box = document.querySelector(target).getBoundingClientRect()
    const main = document.querySelector('main').getBoundingClientRect()
    return Math.min(box.left - main.left, main.right - box.right)
  }, selector)
  expect(gap).toBeGreaterThanOrEqual(16)
}

function pdf(name) {
  return { name, mimeType: 'application/pdf', buffer: PDF }
}

test.describe('accredited flow (page 20, flow 1)', () => {
  test('apply → bank transfer → the path page shows the transfer under review', async ({
    page,
  }, testInfo) => {
    test.setTimeout(60000)
    await signIn(page, testInfo, USERS.ruwad)
    await open(page, '/dashboard', '[data-testid="path-tiles"]')
    const tile = page.locator('[data-testid="path-tile-accredited"]')
    await expect(tile).toHaveAttribute('data-state', 'apply')
    await homeSettled(page)
    await shot(page, '01-home-new-entity', testInfo)
    await tile.locator('[data-testid="path-tile-action-accredited"]').click()
    await expect(page).toHaveURL(/\/paths\/accredited$/)
    await expect(page.locator('[data-testid="path-row-period-20262"]')).toContainText(
      say(testInfo, 'Missed', 'لم يُقدَّم'),
    )
    await shot(page, '02-path-accredited-empty-missed', testInfo)
    await page.locator('[data-testid="path-primary-action"]').click()
    await expect(page).toHaveURL(/\/assessment\/accredited\/pay$/)
    await page.locator('.method-option[data-value="bank_transfer"]').click()
    await page.locator('.checkout__start .btn--primary').click()
    await page
      .locator('input[type="file"][name="proof"]')
      .setInputFiles(pdf('transfer-receipt.pdf'))
    await expect(page.locator('.proof-chip .badge')).toBeVisible()
    await page.locator('.transfer-panel__submit .btn--primary').click()
    await expect(page).toHaveURL(/\/payments\/[0-9a-f-]{36}\/complete$/)
    await closeSuccess(page).catch(() => null)
    await open(page, '/paths/accredited', '[data-testid="path-status-card"]')
    await expect(page.locator('[data-testid="path-primary-action"]')).toContainText(
      say(testInfo, 'View the payment', 'عرض الدفع'),
    )
    await shot(page, '03-path-accredited-transfer-review', testInfo)
  })

  test('draft → submit dialog with the period → success → request tab', async ({
    page,
  }, testInfo) => {
    test.setTimeout(90000)
    await signIn(page, testInfo, USERS.namaa)
    await open(page, '/paths/accredited', '[data-testid="path-status-card"]')
    await shot(page, '04-path-accredited-draft', testInfo)
    await page.locator('[data-testid="path-primary-action"]').click()
    await expect(page).toHaveURL(/\/assessment\/accredited\/form\/9101$/)
    await expectGutter(page, '.portal-area__title')
    await expect(page.locator('.request-card__title')).toContainText('ACC-2026-000004')
    await expect(page.locator('.request-card__title')).not.toContainText(
      say(testInfo, 'Station', 'المحطة'),
    )
    await page.locator('.station-row__button').nth(3).click()
    const governance = page.locator('#accredited-sub-402')
    await governance
      .locator('.evidence-file--refused .evidence-upload__input')
      .setInputFiles(pdf('audited-statements-2025.pdf'))
    await expect(governance.locator('.evidence-file--refused')).toHaveCount(0)
    await page.locator('.station-row__button').nth(7).click()
    const impact = page.locator('#accredited-sub-802')
    await impact
      .locator('textarea')
      .fill('تقيس الجهة أثر برامجها سنويًا وفق منهجية العائد الاجتماعي على الاستثمار.')
    await impact
      .locator('.evidence-slots__slot')
      .first()
      .locator('.evidence-upload__input')
      .setInputFiles(pdf('impact-report-2026.pdf'))
    await expect(impact.locator('.scan-status').first()).toContainText(
      say(testInfo, 'Clean', 'سليم'),
      { timeout: 15000 },
    )
    await page.locator('.accredited-actions .btn--secondary').click()
    await page.locator('.accredited-actions .btn--primary').click()
    const dialog = page.locator('[role="alertdialog"]')
    await expect(dialog.locator('.modal__message')).toHaveText(
      say(testInfo, 'Period: Q4 2026', 'الفترة: الربع الرابع 2026'),
    )
    await shot(page, '05-dialog-submit-period', testInfo)
    await decisionAction(page).click()
    await expect(page).toHaveURL(/\/paths\/accredited\/9101\?tab=request$/)
    await shot(page, '06-dialog-submit-success', testInfo)
    await closeSuccess(page)
    await expect(page.locator('.request-page__badges')).toContainText(
      say(testInfo, 'Submitted', 'مُرسل'),
    )
    await shot(page, '07-request-tab-in-review', testInfo)
  })

  test('result → Kit offer → Kit tab, with the tabs keyboard-operable', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.namaa)
    await open(page, '/paths/accredited/9104', '[data-testid="request-tab-result"]')
    await expect(page.locator('[role="tab"][aria-selected="true"]')).toHaveText(
      say(testInfo, 'Result', 'النتيجة'),
    )
    await expect(page.locator('[data-testid="kit-offer"]')).toBeVisible()
    await resultVisible(page)
    await shot(page, '08-request-tab-result-kit-offer', testInfo)
    await page.locator('[role="tab"][aria-selected="true"]').focus()
    await page.keyboard.press('ArrowRight')
    await expect(page).toHaveURL(/tab=(feedback|request)/)
    await open(page, '/paths/accredited/9104?tab=feedback', '[data-testid="request-tab-feedback"]')
    await shot(page, '09-request-tab-feedback', testInfo)
    await open(
      page,
      '/paths/accredited/9104?tab=certificate',
      '[data-testid="certificate-valid-until"]',
    )
    await shot(page, '10-request-tab-certificate', testInfo)
    await open(page, '/paths/accredited/9104?tab=request', '[data-testid="request-tab-request"]')
    await shot(page, '11-request-tab-completed', testInfo)
    await open(page, '/paths/accredited/9104?tab=tools', '[data-testid="request-tab-tools"]')
    await shot(page, '12-request-tab-tools', testInfo)
    await page.locator('[data-testid="request-tab-tools"]').waitFor()
    await open(page, '/paths/accredited/9104?tab=result', '[data-testid="kit-offer"]')
    await page.locator('[data-testid="kit-offer-action"]').click()
    await expect(page).toHaveURL(/\/bag\/pay$/)
    await signIn(page, testInfo, USERS.albirr)
    await open(page, '/paths/accredited/9102?tab=kit', '[data-testid="request-tab-kit"]')
    await expect(page.locator('[data-testid="request-tab-kit"]')).toHaveAttribute(
      'data-kit-status',
      'active',
    )
    await shot(page, '13-request-tab-kit-active', testInfo)
  })
})

test.describe('self flow (page 20, flow 2)', () => {
  test('start from the self path page and open a result from its list', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.albirr)
    await open(page, '/paths/self', '[data-testid="path-requests"] [data-testid^="path-row-"]')
    await shot(page, '14-path-self', testInfo)
    await page
      .locator('[data-testid="path-requests"] a[href*="/assessment/self/result/"]')
      .first()
      .click()
    await expect(page).toHaveURL(/\/assessment\/self\/result\/\d+$/)
    await signIn(page, testInfo, USERS.ruwad)
    await open(page, '/paths/self', '[data-testid="path-status-card"]')
    await page.locator('[data-testid="path-primary-action"]').click()
    await expect(page).toHaveURL(/\/assessment\/self$/)
  })
})

test.describe('award flow (page 20, flow 3)', () => {
  test('open cycle → apply → advanced form → draft on the path page; masked submitted state', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.albirr)
    await open(page, '/paths/award', '[data-testid="path-cycle-card"]')
    await expect(page.locator('[data-testid="path-status-card"]')).toHaveAttribute(
      'data-state',
      'eligible',
    )
    await shot(page, '15-path-award-eligible', testInfo)
    await page.locator('[data-testid="path-primary-action"]').click()
    await expect(page).toHaveURL(/\/assessment\/advanced$/)
    await page.locator('.advanced-request__actions .btn--primary').click()
    await expect(page).toHaveURL(/\/assessment\/advanced\/form\/\d+$/)
    await navigateInApp(page, '/paths/award')
    await expect(page.locator('[data-testid="path-status-card"]')).toHaveAttribute(
      'data-state',
      'draft',
    )
    await signIn(page, testInfo, USERS.ruwad)
    await open(page, '/paths/award', '[data-testid="path-status-card"]')
    await expect(page.locator('[data-testid="path-status-card"]')).toHaveAttribute(
      'data-state',
      'submitted',
    )
    await shot(page, '16-path-award-submitted', testInfo)
  })
})

test.describe('redirects and mobile sheet', () => {
  test('old deep links land on the path pages and tabs', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.albirr)
    await page.goto('/assessment/history')
    await expect(page).toHaveURL(/\/dashboard$/)
    await page.goto('/assessment/accredited/result/9102')
    await expect(page).toHaveURL(/\/paths\/accredited\/9102\?tab=result$/)
    await page.goto('/assessment/accredited/result/9102/feedback')
    await expect(page).toHaveURL(/\/paths\/accredited\/9102\?tab=feedback$/)
    await expect(page.locator('[data-testid="request-tab-feedback"]')).toBeVisible()
  })

  test('the 390 px «المسارات» sheet traps focus, closes with Esc and opens a path', async ({
    page,
  }, testInfo) => {
    await page.setViewportSize(MOBILE)
    await signIn(page, testInfo, USERS.namaa)
    await open(page, '/dashboard', '[data-testid="path-tiles"]')
    await homeSettled(page)
    await noHorizontalOverflow(page)
    await shot(page, '17-home-mobile', testInfo)
    const tab = page.locator('[data-testid="paths-tab"] button')
    await tab.click()
    const sheet = page.locator('.dropdown-sheet')
    await expect(sheet.locator('.paths-sheet__row')).toHaveCount(3)
    await shot(page, '18-paths-sheet-mobile', testInfo)
    for (let step = 0; step < 6; step += 1) await page.keyboard.press('Tab')
    const inside = await page.evaluate(() =>
      Boolean(document.activeElement?.closest('.dropdown-sheet')),
    )
    expect(inside).toBe(true)
    await page.keyboard.press('Escape')
    await expect(sheet).toHaveCount(0)
    await expect(tab).toBeFocused()
    await tab.click()
    await page.locator('[data-testid="paths-sheet-accredited"]').click()
    await expect(page).toHaveURL(/\/paths\/accredited$/)
    await expect(page.locator('.dropdown-sheet')).toHaveCount(0)
    await noHorizontalOverflow(page)
    await shot(page, '19-path-accredited-mobile', testInfo)
    await open(page, '/paths/accredited/9104', '[data-testid="request-tab-result"]')
    await resultVisible(page)
    await noHorizontalOverflow(page)
    await shot(page, '20-request-result-mobile', testInfo)
  })
})
