import { readFileSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

// Brand colour control: the derived colours with Auto/Custom overrides, the fixed colours and the
// live preview, in Arabic (RTL) and English (LTR). There is no contrast check: the client owns the
// colours it chooses (operator decision 2026-11-22).

const SHOTS = 'e2e/__screenshots__/brand-colors'
const ADMIN = { id: 1, roles: ['system_admin'], name: 'System Admin' }
const LOADED = { timeout: 15_000 }

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffix(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

const TEXT = {
  ar: { auto: 'تلقائي', custom: 'مخصّص', soft: 'الخلفية الفاتحة', contrast: 'التباين' },
  en: { auto: 'Auto', custom: 'Custom', soft: 'Soft background', contrast: 'contrast' },
}

async function openVisualIdentity(page, testInfo) {
  await seedSignedIn(page, ADMIN, { locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto('/admin/branding?tab=visual')
  await expect(page.locator('.brand-derived__row')).toHaveCount(5, LOADED)
  return TEXT[isEn(testInfo) ? 'en' : 'ar']
}

function row(page, index) {
  return page.locator('.brand-derived__row').nth(index)
}

function shellProperty(page, name) {
  return page.evaluate(
    (property) => getComputedStyle(document.documentElement).getPropertyValue(property).trim(),
    name,
  )
}

test('lists the derived colours on auto, previews them and saves a readable override', async ({
  page,
}, testInfo) => {
  const text = await openVisualIdentity(page, testInfo)
  for (let index = 0; index < 5; index += 1) {
    await expect(row(page, index).locator('.badge')).toHaveText(text.auto)
  }
  await expect(page.locator('.brand-fixed__item')).toHaveCount(11)
  await expect(page.locator('.brand-preview [role="tab"]')).toHaveCount(2)
  await page.screenshot({ path: `${SHOTS}/screen-${suffix(testInfo)}.png`, fullPage: true })

  const soft = row(page, 3)
  await expect(soft).toContainText(text.soft)
  await soft.locator('.btn').click()
  await soft.locator('.brand-derived__picker').fill('#e6f4f3')
  await expect(soft.locator('.badge')).toHaveText(text.custom)
  await expect(soft.locator('.brand-derived__hex')).toHaveText('#e6f4f3')
  expect(await shellProperty(page, '--brand-primary-tint')).toBe('#f5fbfb')

  await page.locator('.brand-preview [role="tab"]').nth(1).click()
  await expect(page.locator('.brand-sample--portal')).toBeVisible()
  await page.screenshot({ path: `${SHOTS}/override-${suffix(testInfo)}.png`, fullPage: true })

  await page.locator('.branding-page__actions .btn--primary').click()
  await expect.poll(() => shellProperty(page, '--brand-primary-tint')).toBe('#e6f4f3')
  await expect(row(page, 3).locator('.badge')).toHaveText(text.custom)
})

test('saves a low-contrast custom colour with no contrast check', async ({ page }, testInfo) => {
  const text = await openVisualIdentity(page, testInfo)
  const onSecondary = row(page, 4)
  await onSecondary.locator('.btn').click()
  await onSecondary.locator('.brand-derived__picker').fill('#6b8a8c')
  await expect(onSecondary.locator('.badge')).toHaveText(text.custom)
  await expect(page.locator('.branding-editor [role="alert"]')).toHaveCount(0)
  await expect(page.locator('.brand-derived')).not.toContainText(text.contrast)
  await expect(page.locator('.brand-derived')).not.toContainText(':1')
  const save = page.locator('.branding-page__actions .btn--primary')
  await expect(save).toBeEnabled()
  await page.screenshot({ path: `${SHOTS}/low-contrast-${suffix(testInfo)}.png`, fullPage: true })

  await save.click()
  await expect.poll(() => shellProperty(page, '--brand-on-secondary')).toBe('#6b8a8c')
  await expect(row(page, 4).locator('.badge')).toHaveText(text.custom)
  await expect(save).toBeDisabled()
})

// The production SPA policy (deploy/nginx/moashir-spa-headers.conf), minus
// `upgrade-insecure-requests`: the dev server is plain http. `style-src 'self'` refuses any
// inline <style>, so the saved scheme must arrive through CSSOM.
const PRODUCTION_CSP = readFileSync(
  new URL('../../deploy/nginx/moashir-spa-headers.conf', import.meta.url),
  'utf8',
)
  .match(/Content-Security-Policy "([^"]+)"/)[1]
  .replace(/;\s*upgrade-insecure-requests/, '')

async function serveWithProductionCsp(page) {
  await page.route(
    (url) => url.pathname.startsWith('/admin/'),
    async (route) => {
      const response = await route.fetch()
      await route.fulfill({
        response,
        headers: { ...response.headers(), 'content-security-policy': PRODUCTION_CSP },
      })
    },
  )
}

test('applies a saved brand scheme under the production CSP (no inline style)', async ({
  page,
}, testInfo) => {
  expect(PRODUCTION_CSP).toContain("style-src 'self'")
  expect(PRODUCTION_CSP).not.toContain('unsafe-inline')
  await serveWithProductionCsp(page)
  await openVisualIdentity(page, testInfo)
  expect(await shellProperty(page, '--brand-primary')).toBe('#98d8d3')

  await page.locator('.brand-color-field[data-role="primary"] input[type="text"]').fill('#1c5f9a')
  await page.locator('.branding-page__actions .btn--primary').click()

  await expect.poll(() => shellProperty(page, '--brand-primary')).toBe('#1c5f9a')
  expect(await shellProperty(page, '--brand-primary-hover')).toBe('#1b5589')
  const inlineBrandStyles = await page.evaluate(
    () =>
      [...document.querySelectorAll('style')].filter((style) =>
        style.textContent.includes('#1c5f9a'),
      ).length,
  )
  expect(inlineBrandStyles).toBe(0)
})
