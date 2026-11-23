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

function railField(page, role) {
  return page.locator(`.brand-rail-field[data-role="${role}"]`)
}

function backgroundImage(page, selector) {
  return page.locator(selector).evaluate((element) => getComputedStyle(element).backgroundImage)
}

const NAVY = 'rgb(29, 43, 79)'

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
  await expect(page.locator('.brand-fixed')).toHaveCount(0)
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

// Sidebar gradient (look 3): two colours, a live preview on the screen, nothing applied before save.
test('previews a chosen sidebar gradient on the screen only, then applies it on save', async ({
  page,
}, testInfo) => {
  const text = await openVisualIdentity(page, testInfo)
  await expect(railField(page, 'top').locator('.badge')).toHaveText(text.auto)
  await expect(railField(page, 'top').locator('input[type="text"]')).toHaveValue('#0a2325')
  await expect(railField(page, 'bottom').locator('input[type="text"]')).toHaveValue('#2b4d4d')
  expect(await backgroundImage(page, '.sidebar')).toContain('rgb(10, 35, 37)')

  await railField(page, 'top').locator('input[type="text"]').fill('#1d2b4f')
  await railField(page, 'bottom').locator('input[type="text"]').fill('#3c6e71')
  await expect(railField(page, 'top').locator('.badge')).toHaveText(text.custom)
  await expect.poll(() => backgroundImage(page, '.brand-rail__sample')).toContain(NAVY)
  expect(await backgroundImage(page, '.brand-sample__nav--rail')).toContain(NAVY)
  expect(await backgroundImage(page, '.sidebar')).not.toContain(NAVY)
  expect(await shellProperty(page, '--brand-rail-top')).toBe('#0a2325')
  await page.screenshot({ path: `${SHOTS}/rail-preview-${suffix(testInfo)}.png`, fullPage: true })

  await page.locator('.branding-page__actions .btn--primary').click()
  await expect.poll(() => shellProperty(page, '--brand-rail-top')).toBe('#1d2b4f')
  await expect.poll(() => backgroundImage(page, '.sidebar')).toContain(NAVY)
  await page.screenshot({ path: `${SHOTS}/rail-saved-${suffix(testInfo)}.png` })
})

test('saves any two sidebar colours with no contrast check: the client owns the result', async ({
  page,
}, testInfo) => {
  const text = await openVisualIdentity(page, testInfo)
  await railField(page, 'top').locator('input[type="text"]').fill('#2b1055')
  await railField(page, 'bottom').locator('input[type="text"]').fill('#9fb8ec')
  await expect(page.locator('.brand-rail [role="alert"]')).toHaveCount(0)
  await page.screenshot({ path: `${SHOTS}/rail-any-pair-${suffix(testInfo)}.png`, fullPage: true })
  await page.locator('.branding-page__actions .btn--primary').click()
  await expect.poll(() => shellProperty(page, '--brand-rail-top')).toBe('#2b1055')

  await railField(page, 'top').locator('.btn').click()
  await railField(page, 'bottom').locator('.btn').click()
  await expect(railField(page, 'bottom').locator('.badge')).toHaveText(text.auto)
})

test('draws the saved sidebar gradient under the production CSP (no inline style)', async ({
  page,
}, testInfo) => {
  await serveWithProductionCsp(page)
  await openVisualIdentity(page, testInfo)
  await railField(page, 'top').locator('input[type="text"]').fill('#1d2b4f')
  await railField(page, 'bottom').locator('input[type="text"]').fill('#3c6e71')
  await page.locator('.branding-page__actions .btn--primary').click()

  // The dev server's own <style> tags are refused under this policy; the brand scheme is not.
  await expect.poll(() => shellProperty(page, '--brand-rail-top')).toBe('#1d2b4f')
  expect(await shellProperty(page, '--brand-rail-gradient')).toMatch(
    /^linear-gradient\(180deg, #1d2b4f 0%, .+ #3c6e71 100%\)$/,
  )
  await expect(page.locator('.brand-rail__idea .brand-rail__swatch').first()).not.toHaveCSS(
    'background-image',
    'none',
  )
  const inlineRailStyles = await page.evaluate(
    () =>
      [...document.querySelectorAll('style, [style]')].filter((node) =>
        /#1d2b4f|rgb\(29, 43, 79\)/.test(node.textContent + (node.getAttribute('style') ?? '')),
      ).length,
  )
  expect(inlineRailStyles).toBe(0)
})
