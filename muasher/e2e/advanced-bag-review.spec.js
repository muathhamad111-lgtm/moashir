/* eslint-disable playwright/no-wait-for-timeout, playwright/expect-expect */
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

function localeFor(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function suffixFor(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en-ltr' : 'ar-rtl'
}

function seed(page, locale, { role, entityId, committee = false } = {}) {
  return seedSignedIn(
    page,
    {
      id: committee ? 'jur-9404' : (entityId ?? 7000),
      roles: [role],
      entityId: entityId ?? null,
      name: { ar: 'مستخدم الاختبار', en: 'Test User' },
      ...(committee ? { committeeCycles: ['cycle-committee-2026'] } : {}),
    },
    { locale },
  )
}

function isBenignNetworkNoise(text) {
  return /Failed to load resource: the server responded with a status of (403|404|500|502|503)/.test(text)
}

function collectConsoleErrors(page) {
  const errors = []
  page.on('console', (msg) => {
    if (msg.type() !== 'error') return
    if (isBenignNetworkNoise(msg.text())) return
    errors.push(msg.text())
  })
  page.on('pageerror', (err) => errors.push(String(err)))
  return errors
}

async function gotoWithRetry(page, path, selector) {
  await page.goto(path)
  for (let attempt = 0; attempt < 10; attempt += 1) {
    if (await page.locator(selector).first().isVisible().catch(() => false)) return
    const retry = page.locator('.error-state__retry button, .error-state__retry .btn')
    if (await retry.first().isVisible().catch(() => false)) await retry.first().click().catch(() => {})
    await page.waitForTimeout(500)
  }
}

async function reviewPage(page, testInfo, { path, titleSelector, name, session, okSelector }) {
  const errors = collectConsoleErrors(page)
  await seed(page, localeFor(testInfo), session)
  const waitSelector = okSelector ?? titleSelector
  await gotoWithRetry(page, path, waitSelector)
  await expect(page.locator(waitSelector).first()).toBeVisible({ timeout: 15000 })
  await page.waitForTimeout(400)
  await page.screenshot({ path: `test-results/vr-${name}-${suffixFor(testInfo)}.png`, fullPage: true })
  expect(errors, `console errors on ${path}: ${errors.join(' | ')}`).toHaveLength(0)
}

const ENTITY = { role: 'entity', entityId: 5001 }
const STAFF = { role: 'system_admin' }
const ASSESSOR = { role: 'assessor' }
const COMMITTEE = { role: 'assessor', committee: true }

test.describe('Bag', () => {
  test('bag index (entity) renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/bag',
      titleSelector: '.bag__title',
      name: 'bag-index',
      session: ENTITY,
    })
  })

  test('bag manage (assessor/consultant) renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/bag/manage',
      titleSelector: '.bag-manage__title',
      okSelector: '.bag-manage__title, .empty-state',
      name: 'bag-manage',
      session: ASSESSOR,
    })
  })

  test('bag comparison (before/after) renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/bag/comparison',
      titleSelector: '.bag-comparison__title',
      name: 'bag-comparison',
      session: ENTITY,
    })
  })

  test('bag requests (follower queue) renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/bag/requests',
      titleSelector: '.bag-requests__title',
      name: 'bag-requests',
      session: STAFF,
    })
  })

  test('bag evaluator queue renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/bag/evaluator',
      titleSelector: '.bag-requests__title',
      okSelector: '.bag-requests__title, .empty-state',
      name: 'bag-evaluator',
      session: ASSESSOR,
    })
  })

  test('bag public details page renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/excellence-bag',
      titleSelector: '.bag-intro__title',
      name: 'bag-details',
      session: ENTITY,
    })
  })

  test('bag checkout (payment) renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/bag/pay',
      titleSelector: '.checkout__title',
      name: 'bag-checkout',
      session: ENTITY,
    })
  })

  test('my payments (receipts + VAT) renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/payments',
      titleSelector: '.payments-page__title',
      okSelector: '.payments-page__title',
      name: 'my-payments',
      session: ENTITY,
    })
  })
})

test.describe('Advanced — entity', () => {
  test('advanced intro renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/assessment/advanced',
      titleSelector: '.advanced-intro__title',
      name: 'advanced-intro',
      session: ENTITY,
    })
  })

  test('advanced form (9207, read-only) renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/assessment/advanced/form/9207',
      titleSelector: '.accredited-form__title',
      name: 'advanced-form',
      session: ENTITY,
    })
  })

  test('advanced result (9302) renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/assessment/advanced/result/9302',
      titleSelector: '.advanced-result__title',
      name: 'advanced-result',
      session: ENTITY,
    })
  })
})

test.describe('Advanced — staff', () => {
  test('award cycles list renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/admin/award/cycles',
      titleSelector: '.cycles-page__title',
      name: 'award-cycles',
      session: STAFF,
    })
  })

  test('award cycle editor (31) renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/admin/award/cycles/31',
      titleSelector: '.cycle-editor__title',
      name: 'award-cycle-setup',
      session: STAFF,
    })
  })

  test('award filtering renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/admin/award/filtering',
      titleSelector: '.cycles-page__title',
      name: 'award-filtering',
      session: STAFF,
    })
  })

  test('award committee queue renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/admin/award/committee',
      titleSelector: '.committee-queue__title',
      name: 'award-committee',
      session: COMMITTEE,
    })
  })

  test('award committee score (9401) renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/admin/award/committee/score/9401',
      titleSelector: '.committee-score__title',
      name: 'award-committee-score',
      session: COMMITTEE,
    })
  })

  test('award secretary renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/admin/award/secretary',
      titleSelector: '.secretary__title',
      name: 'award-secretary',
      session: STAFF,
    })
  })

  test('award committee management (cycle-scoped) renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/admin/award/committee/manage?cycle=cycle-committee-2026',
      titleSelector: '.committee-management__title',
      okSelector: '.committee-management__title, .empty-state',
      name: 'award-committee-management',
      session: STAFF,
    })
  })

  test('admin financial operations renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/finance/transactions',
      titleSelector: '.finance-header__title',
      name: 'admin-operations',
      session: STAFF,
    })
  })

  test('award results (regional winners) renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/admin/award/results',
      titleSelector: '.award-results__title',
      name: 'award-results',
      session: STAFF,
    })
  })

  test('national award renders clean', async ({ page }, testInfo) => {
    await reviewPage(page, testInfo, {
      path: '/admin/award/national',
      titleSelector: '.award-national__title',
      okSelector: '.award-national__title, .empty-state',
      name: 'award-national',
      session: STAFF,
    })
  })
})
