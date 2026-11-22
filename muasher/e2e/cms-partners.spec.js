import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { closeSuccess, decisionAction } from './support/dialogs'

const FIRST_PARTNER = {
  ar: 'المركز الوطني لتنمية القطاع غير الربحي',
  en: 'National Center for Non-Profit Sector Development',
}
const PARTNER_LINK = 'https://ncnp.gov.sa'
const SAFE_LOGO = Buffer.from(
  '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="100" viewBox="0 0 240 100"><rect width="240" height="100" fill="#0d6b6b"/><text x="20" y="60" fill="#fff">NCNP</text></svg>',
)
const UNSAFE_LOGO = Buffer.from(
  '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="10" height="10"/></svg>',
)

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function svgFile(name, buffer) {
  return { name, mimeType: 'image/svg+xml', buffer }
}

async function openPartnersItems(page, testInfo) {
  await seedSignedIn(
    page,
    { roles: ['content_officer'], name: 'Content Officer' },
    { locale: localeOf(testInfo) },
  )
  await page.goto('/admin/settings/homepage')
  const row = page
    .locator('.sortable-list__row')
    .filter({ hasText: /partners/ })
    .first()
  await expect(row).toBeVisible({ timeout: 15000 })
  await row.locator('.cms-section-row input[type="checkbox"]').check()
  await expect(row.locator('.cms-section-row .badge--info')).toBeVisible()
  await row.locator('.cms-section-row__actions button').nth(1).click()
  const items = page.locator('.modal').filter({ has: page.locator('.cms-items') })
  await expect(items.locator('.cms-items__draft')).toBeVisible()
  return items
}

async function editPartner(page, items, index) {
  await items.locator('.cms-items__row .btn--secondary').nth(index).click()
  return page.locator('.modal').filter({ has: page.locator('input[name="title.ar"]') })
}

test('a partner gets an SVG logo and an https link, and the published home shows the clickable logo', async ({
  page,
}, testInfo) => {
  const locale = localeOf(testInfo)
  const items = await openPartnersItems(page, testInfo)
  await expect(items.locator('.cms-items__row .badge--warning')).toHaveCount(4)

  const form = await editPartner(page, items, 0)
  await expect(form.locator('input[name="title.ar"]')).toHaveValue(FIRST_PARTNER.ar)
  await expect(form.locator('input[name="icon"], textarea')).toHaveCount(0)
  await form.locator('input[type="file"]').setInputFiles(svgFile('ncnp.svg', SAFE_LOGO))
  await expect(form.locator('.content-image-field__image')).toBeVisible()
  await form.locator('input[name="ctaUrl"]').fill(PARTNER_LINK)
  await form.locator('.modal__footer .btn--primary').click()
  await expect(form).toBeHidden()
  await expect(
    items.locator('.cms-items__row').first().locator('img.cms-items__logo'),
  ).toBeVisible()
  await expect(items.locator('.cms-items__row .badge--warning')).toHaveCount(3)
  await expect(items.locator('.cms-items__draft .badge')).toBeVisible()
  await page.keyboard.press('Escape')

  await page.locator('.publication-control .btn--primary').click()
  await decisionAction(page).click()
  await closeSuccess(page)
  await page.locator('.publication-control__view').click()

  const partners = page.locator('#partners')
  await expect(partners).toBeVisible({ timeout: 15000 })
  await expect(partners.locator('.lp-partners__item')).toHaveCount(1)
  const link = partners.locator('a.lp-partners__logo')
  await expect(link).toHaveAttribute('href', PARTNER_LINK)
  await expect(link).toHaveAttribute('target', '_blank')
  await expect(link).toHaveAttribute('rel', /noopener/)
  const logo = link.locator('img')
  await expect(logo).toHaveAttribute('alt', FIRST_PARTNER[locale])
  await expect(logo).toHaveAttribute('loading', 'lazy')
  await logo.scrollIntoViewIfNeeded()
  await expect.poll(() => logo.evaluate((image) => image.naturalWidth)).toBeGreaterThan(0)
})

test('an unsafe SVG logo and a plain http link are refused in the partner form', async ({
  page,
}, testInfo) => {
  const items = await openPartnersItems(page, testInfo)
  const form = await editPartner(page, items, 1)
  await form.locator('input[type="file"]').setInputFiles(svgFile('bad.svg', UNSAFE_LOGO))
  await expect(form.locator('.content-image-field__image')).toHaveCount(0)
  await expect(form.locator('.content-image-field .field__error')).toContainText('SVG')
  await form.locator('input[name="ctaUrl"]').fill('http://example.org')
  await form.locator('.modal__footer .btn--primary').click()
  await expect(form).toBeVisible()
  await expect(form.locator('input[name="ctaUrl"]')).toHaveAttribute('aria-invalid', 'true')
})
