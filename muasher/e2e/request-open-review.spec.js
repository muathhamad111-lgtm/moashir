import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

// Live bug 2026-11-22: on the request page «بدء التقييم» did nothing, while the assigned list opened the review.
const ASSESSOR = { id: 10, roles: ['assessor'] }
const FOLLOWER = { id: 8, roles: ['request_follower', 'filtering_officer'] }

const CASES = [
  { name: 'the assessor starts an accredited assessment', user: ASSESSOR, id: 9107 },
  { name: 'the assessor opens an advanced initial assessment', user: ASSESSOR, id: 9202 },
  { name: 'the follower opens the review of an assigned request', user: FOLLOWER, id: 9108 },
]

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

for (const { name, user, id } of CASES) {
  test(`${name} from the request page header`, async ({ page }, testInfo) => {
    const errors = []
    page.on('pageerror', (error) => errors.push(error.message))
    await seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar' })
    await page.goto(`/admin/requests/${id}`)
    const action = page.locator('[data-testid="request-open-review"]')
    await expect(action).toBeVisible()
    await action.click()
    await expect(page).toHaveURL(new RegExp(`/admin/requests/${id}/review$`))
    await expect(page.locator('.review-header, .review-workspace').first()).toBeVisible()
    expect(errors).toEqual([])
  })
}

test('the assessor sees «بدء التقييم» on a newly assigned request and it opens the review', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, ASSESSOR, { locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto('/admin/requests/9107')
  const action = page.locator('[data-testid="request-open-review"]')
  await expect(action).toHaveText(isEn(testInfo) ? 'Start the assessment' : 'بدء التقييم')
  await action.click()
  await expect(page.locator('[data-testid="review-start"]')).toBeVisible()
})
