import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

// The staff request record, layout A (request page v4.1, operator «معتمد» 2026-11-26): the actions in the row above,
// one summary card (identity, the status once with «منذ», then «الإسناد» | «المواعيد»), the result ribbon once
// completed, and the tabs; the tab cards carry their own actions in their header. Same layout on the Kit page.
const IGNORED_CONSOLE = /Failed to load resource|40\d|50\d/i
const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const ADMIN = { id: 1, roles: ['system_admin'] }
const SULTAN = { id: 10, roles: ['assessor'] }

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function text(testInfo, en, ar) {
  return isEn(testInfo) ? en : ar
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function trackErrors(page) {
  const errors = []
  page.on('console', (message) => {
    if (message.type() === 'error' && !IGNORED_CONSOLE.test(message.text()))
      errors.push(message.text())
  })
  page.on('pageerror', (error) => errors.push(error.message))
  return errors
}

async function open(page, testInfo, user, path, ready) {
  await seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto(path)
  await expect(page.locator(ready).first()).toBeVisible()
}

function noHorizontalOverflow(page) {
  return page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
}

function groupTitles(page) {
  return page.locator('.record-summary .record-group h2')
}

for (const [viewport, size] of Object.entries({ desktop: DESKTOP, mobile: MOBILE })) {
  test.describe(`request record layout A (${viewport})`, () => {
    test.use({ viewport: size })

    test('an accredited request under review reads in one order with the status once', async ({
      page,
    }, testInfo) => {
      const errors = trackErrors(page)
      await open(page, testInfo, ADMIN, '/admin/requests/9108', '.record-summary h1')
      await expect(page.locator('.record-summary h1')).toHaveText('ACC-2026-000008')
      await expect(page.locator('.record-identity__kind')).toHaveText(
        text(testInfo, 'Accredited assessment', 'التقييم المعتمد'),
      )
      await expect(page.locator('.record-status__badge')).toHaveCount(1)
      await expect(page.locator('.record-status__since')).toContainText(
        text(testInfo, 'Since', 'منذ'),
      )
      await expect(groupTitles(page)).toHaveText([
        text(testInfo, 'Assignment', 'الإسناد'),
        text(testInfo, 'Dates', 'المواعيد'),
      ])
      await expect(page.locator('.record-summary .record-fact__note').first()).toHaveText(
        text(testInfo, 'Reassigned', 'أُعيد الإسناد'),
      )
      await expect(page.locator('.record-summary__bar .request-actions')).toBeVisible()
      await expect(page.locator('.request-detail__tabs [role="tab"]')).toHaveText([
        text(testInfo, 'Overview', 'نظرة عامة'),
        text(testInfo, 'Payment', 'الدفع'),
        text(testInfo, 'History', 'السجل'),
      ])
      expect(await noHorizontalOverflow(page)).toBeLessThanOrEqual(0)
      await page.screenshot({
        path: `test-results/request-page-under-review-${viewport}-${suffixOf(testInfo)}.png`,
        fullPage: true,
      })
      expect(errors).toEqual([])
    })

    test('a completed request shows its result ribbon and the certificate actions in the card header', async ({
      page,
    }, testInfo) => {
      await open(
        page,
        testInfo,
        ADMIN,
        '/admin/requests/9104?tab=certificate',
        '[data-certificate="current"]',
      )
      await expect(page.locator('[data-testid="level-ribbon"]')).toContainText(
        text(testInfo, 'Request result', 'نتيجة الطلب'),
      )
      const header = page.locator('.request-certificate .record-card-header')
      await expect(header.locator('h2')).toHaveText(text(testInfo, 'Certificate', 'الشهادة'))
      await expect(header.locator('.record-card-header__actions .btn')).toHaveText([
        text(testInfo, 'Download the certificate (PDF)', 'تنزيل الشهادة PDF'),
        text(testInfo, 'Re-issue', 'إعادة الإصدار'),
        text(testInfo, 'Revoke the certificate', 'إلغاء الشهادة'),
      ])
      await expect(page.locator('[data-certificate="current"] .btn')).toHaveCount(0)
      expect(await noHorizontalOverflow(page)).toBeLessThanOrEqual(0)
      await page.screenshot({
        path: `test-results/request-page-certificate-${viewport}-${suffixOf(testInfo)}.png`,
        fullPage: true,
      })
    })

    test('the payment tab carries its actions in the card header', async ({ page }, testInfo) => {
      await open(
        page,
        testInfo,
        ADMIN,
        '/admin/requests/9108?tab=payment',
        '[data-testid="request-payment-card"]',
      )
      const actions = page.locator(
        '[data-testid="request-payment-card"] .record-card-header__actions .btn',
      )
      await expect(actions.first()).toHaveText(
        text(testInfo, 'Download the payment receipt', 'تنزيل إيصال الدفع'),
      )
      expect(await noHorizontalOverflow(page)).toBeLessThanOrEqual(0)
    })

    test('an advanced request shows no award stage while the award build is on hold', async ({
      page,
    }, testInfo) => {
      await open(page, testInfo, ADMIN, '/admin/requests/9202', '.record-summary h1')
      await expect(page.locator('.record-summary h1')).toHaveText('ADV-2026-000002')
      await expect(page.locator('.request-overview__data')).not.toContainText(
        text(testInfo, 'Award stage', 'مرحلة الجائزة'),
      )
      expect(await noHorizontalOverflow(page)).toBeLessThanOrEqual(0)
    })

    test('the Kit plan page uses the same summary, titled by the Kit number', async ({
      page,
    }, testInfo) => {
      await open(page, testInfo, SULTAN, '/bag/manage/bag-req-7003', '.record-status')
      await expect(page.locator('.record-summary h1')).toHaveText('KIT-2026-000004')
      await expect(page.locator('.record-identity__kind')).toHaveText(
        text(testInfo, 'Excellence Kit', 'حقيبة التميّز'),
      )
      await expect(groupTitles(page)).toHaveText([
        text(testInfo, 'Assignment', 'الإسناد'),
        text(testInfo, 'Dates', 'المواعيد'),
      ])
      expect(await noHorizontalOverflow(page)).toBeLessThanOrEqual(0)
      await page.screenshot({
        path: `test-results/request-page-kit-${viewport}-${suffixOf(testInfo)}.png`,
        fullPage: true,
      })
    })
  })
}
