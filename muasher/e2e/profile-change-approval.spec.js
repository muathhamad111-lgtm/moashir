import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'
import {
  PROFILE_APPROVER,
  openPendingProfileChange,
  saveProfileDecision,
} from './support/profileChanges'

const NAMAA = {
  id: 6001,
  roles: ['entity'],
  entityId: 6001,
  name: { ar: 'نورة بنت فهد العتيبي', en: 'Noura Fahad Al-Otaibi' },
}
const REASON = 'العدد لا يطابق التقرير السنوي المعتمد.'

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function text(testInfo, ar, en) {
  return localeOf(testInfo) === 'en' ? en : ar
}

function governanceOf(page) {
  return page.locator('[data-section="governance"]')
}

async function openProfile(page, testInfo) {
  await seedSignedIn(page, NAMAA, { locale: localeOf(testInfo) })
  await page.goto('/entity/profile')
  await expect(page.getByTestId('profile-completeness')).toBeVisible()
  await settleFonts(page)
}

async function proposeGovernance(page, testInfo, values) {
  await openProfile(page, testInfo)
  const governance = governanceOf(page)
  await governance.locator('.profile-section__edit').click()
  for (const [name, value] of Object.entries(values)) {
    await governance.locator(`[name="${name}"]`).fill(value)
  }
  await governance.locator('button[type="submit"]').click()
  await expect(page.getByTestId('pending-profile-change')).toBeVisible()
}

test('a profile edit waits for approval, then applies once staff approve it', async ({
  page,
}, testInfo) => {
  await proposeGovernance(page, testInfo, { employeesCount: '77' })
  await expect(page.getByTestId('pending-profile-change')).toContainText(
    text(testInfo, 'بانتظار اعتماد الإدارة', 'Awaiting the administration’s approval'),
  )
  await expect(governanceOf(page).getByTestId('section-pending')).toBeVisible()
  await expect(governanceOf(page)).toContainText('46')
  await expect(governanceOf(page)).not.toContainText('77')

  const review = await openPendingProfileChange(page, localeOf(testInfo))
  await expect(review).toContainText('77')
  await saveProfileDecision(page, localeOf(testInfo))

  await openProfile(page, testInfo)
  await expect(page.getByTestId('pending-profile-change')).toHaveCount(0)
  await expect(governanceOf(page)).toContainText('77')
})

test('staff approve one field and reject the other with a reason', async ({ page }, testInfo) => {
  await proposeGovernance(page, testInfo, {
    employeesCount: '77',
    annualBeneficiariesCount: '9100',
  })

  const review = await openPendingProfileChange(page, localeOf(testInfo))
  await review.locator('input[name="approve-annual_beneficiaries_count"]').uncheck()
  await page
    .getByRole('button', { name: text(testInfo, 'حفظ القرار', 'Save the decision') })
    .click()
  await expect(review).toContainText(
    text(testInfo, 'اكتب سبب الرفض', 'Write the reason for rejection'),
  )
  await review.locator('textarea[name="reason"]').fill(REASON)
  await saveProfileDecision(page, localeOf(testInfo))

  await openProfile(page, testInfo)
  await expect(governanceOf(page)).toContainText('77')
  await governanceOf(page).locator('.profile-section__history').click()
  const drawer = page.getByTestId('profile-history-drawer')
  await drawer
    .getByRole('button', { name: text(testInfo, 'طلبات التعديل', 'Change requests') })
    .click()
  const requests = drawer.getByTestId('profile-change-requests')
  await expect(requests).toContainText(REASON)
  await expect(requests).toContainText(text(testInfo, 'معتمد جزئيًا', 'Partially approved'))
})

test('the entity cancels its pending request and keeps the approved profile', async ({
  page,
}, testInfo) => {
  await proposeGovernance(page, testInfo, { employeesCount: '77' })
  const cancel = text(testInfo, 'إلغاء الطلب', 'Cancel the request')
  await page.getByTestId('pending-profile-change').getByRole('button', { name: cancel }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: cancel }).click()
  await expect(page.getByTestId('pending-profile-change')).toHaveCount(0)
  await expect(governanceOf(page).getByTestId('section-pending')).toHaveCount(0)
  await expect(governanceOf(page)).toContainText('46')

  await seedSignedIn(page, PROFILE_APPROVER, { locale: localeOf(testInfo) })
  await page.goto('/admin/entities/profile-changes')
  await expect(
    page.getByText(
      text(
        testInfo,
        'لا توجد طلبات تعديل في هذه القائمة',
        'No profile change requests in this list',
      ),
    ),
  ).toBeVisible()
})
