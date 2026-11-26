import { expect } from '@playwright/test'
import { seedSignedIn } from './session'

export const PROFILE_APPROVER = {
  id: 2,
  roles: ['system_admin'],
  name: { ar: 'منيرة بنت خالد العتيبي', en: 'Munira Al-Otaibi' },
}

const LABELS = {
  ar: { review: 'مراجعة', save: 'حفظ القرار', saved: 'حُفظ القرار وأُبلغت الجهة.' },
  en: {
    review: 'Review',
    save: 'Save the decision',
    saved: 'The decision was saved and the entity was notified.',
  },
}

/** Signs in as the approver and opens the first pending profile change request for review. */
export async function openPendingProfileChange(page, locale) {
  await seedSignedIn(page, PROFILE_APPROVER, { locale })
  await page.goto('/admin/entities/profile-changes')
  await page.getByRole('button', { name: LABELS[locale].review, exact: true }).first().click()
  const review = page.getByTestId('profile-change-review')
  await expect(review.locator('.change-review__row').first()).toBeVisible()
  return review
}

export async function saveProfileDecision(page, locale) {
  await page.getByRole('button', { name: LABELS[locale].save }).click()
  await expect(page.getByText(LABELS[locale].saved)).toBeVisible()
}

/** Approves every item of the first pending profile change request, as the approver. */
export async function approvePendingProfileChange(page, locale) {
  await openPendingProfileChange(page, locale)
  await saveProfileDecision(page, locale)
}
