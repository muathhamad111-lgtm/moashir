import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

const TRANSFERS = {
  incompleteBilling: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000023',
  completeBilling: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000021',
}

const USERS = {
  albirr: {
    id: 5001,
    roles: ['entity'],
    entityId: 5001,
    name: { ar: 'أحمد بن سالم القحطاني', en: 'Ahmed Salem Al-Qahtani' },
  },
  ataa: {
    id: 6004,
    roles: ['entity'],
    entityId: 6004,
    name: { ar: 'سارة بنت محمد الحربي', en: 'Sara Mohammed Al-Harbi' },
  },
  finance: { id: 22, roles: ['finance_officer'] },
}

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function signIn(page, testInfo, user) {
  return seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar' })
}

const billing = (page) => page.locator('[data-section="billing"]')

test('the entity answers «yes» to VAT, sees the number and certificate fields and sends them for approval', async ({
  page,
}, testInfo) => {
  await signIn(page, testInfo, USERS.albirr)
  await page.goto('/entity/profile')
  await expect(billing(page)).toBeVisible()
  await expect(billing(page).locator('[data-field="vatNumber"]')).toHaveCount(0)

  await billing(page).locator('.profile-section__edit').click()
  await expect(billing(page).locator('input[name="vatNumber"]')).toHaveCount(0)
  await billing(page).locator('input[name="vatRegistered"][value="yes"]').check()
  const vatNumber = billing(page).locator('input[name="vatNumber"]')
  await expect(vatNumber).toBeVisible()
  await expect(billing(page).getByTestId('vat-certificate-upload')).toBeVisible()

  await vatNumber.fill('210123456700003')
  await billing(page).locator('button[type="submit"]').click()
  await expect(vatNumber).toHaveAttribute('aria-invalid', 'true')
  await expect(vatNumber).toHaveAttribute('aria-describedby', /\S/)

  await vatNumber.fill('٣٠٠٠٥٠٠١٠٠٠٠٠٠٣')
  await billing(page).locator('button[type="submit"]').click()
  await expect(billing(page).locator('.profile-section__status-text--success')).toBeVisible()
  await expect(billing(page).getByTestId('section-pending')).toBeVisible()
  await expect(billing(page).locator('[data-field="vatNumber"]')).toHaveCount(0)
  await expect(billing(page).getByTestId('profile-section-state')).toHaveClass(/--complete/)
})

test('the payment page warns about incomplete billing details and links to the profile', async ({
  page,
}, testInfo) => {
  await signIn(page, testInfo, USERS.ataa)
  await page.goto('/assessment/accredited/pay')
  const notice = page.getByTestId('billing-details-notice')
  await expect(notice).toBeVisible()
  await notice.locator('a').click()
  await expect(page).toHaveURL(/\/entity\/profile#profile-section-billing$/)
})

test('Finance sees the billing card and the incomplete flag on the transfer review', async ({
  page,
}, testInfo) => {
  await signIn(page, testInfo, USERS.finance)
  await page.goto(`/finance/transfers/${TRANSFERS.incompleteBilling}`)
  const card = page.getByTestId('transfer-billing')
  await expect(card).toBeVisible()
  await expect(card.getByTestId('transfer-billing-incomplete')).toBeVisible()

  await page.goto(`/finance/transfers/${TRANSFERS.completeBilling}`)
  await expect(page.getByTestId('transfer-billing')).toBeVisible()
  await expect(page.getByTestId('transfer-billing-incomplete')).toHaveCount(0)
})
