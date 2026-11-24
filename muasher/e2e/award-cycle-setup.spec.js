import { test, expect } from '@playwright/test'
import { seedPreferences, seedSignedIn } from './support/session'

const PHONE = { width: 390, height: 844 }
const DESKTOP = { width: 1280, height: 800 }
const LOAD_TIMEOUT = 15_000
const SYSTEM_ADMIN = { id: 1, roles: ['system_admin'] }
const RUNNING_CYCLE = 31

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function say(testInfo, en, ar) {
  return isEn(testInfo) ? en : ar
}

async function shot(page, name, testInfo) {
  const suffix = isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
  await page.screenshot({ path: `test-results/${name}-${suffix}.png`, fullPage: true })
}

async function signIn(page, testInfo, viewport) {
  await page.setViewportSize(viewport)
  await seedSignedIn(page, SYSTEM_ADMIN, { locale: isEn(testInfo) ? 'en' : 'ar' })
}

function pageOverflow(page) {
  return page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
}

async function expectStep(page, testInfo, number, step) {
  await expect(page.locator('.cycle-setup__step-counter')).toHaveText(
    say(testInfo, `(Step ${number} of 7)`, `(الخطوة ${number} من 7)`),
  )
  if (step) await expect(page.locator(`form[data-step="${step}"]`)).toBeVisible()
}

async function next(page) {
  await page.locator('.cycle-setup__next').click()
}

async function fillIdentity(page, testInfo) {
  await expectStep(page, testInfo, 1, 'identity')
  await next(page)
  await expectStep(page, testInfo, 1, 'identity')
  await page.locator('input[name="name.ar"]').fill('دورة جائزة مؤشر للمناطق الشمالية')
  await page.locator('input[name="name.en"]').fill('Moashir Award for the Northern Regions')
  await page
    .locator('textarea[name="description.ar"]')
    .fill('تكرّم هذه الدورة الجهات المتميزة في المناطق الشمالية.\n\nتُعلن النتائج بعد اعتمادها.')
  await page
    .locator('textarea[name="description.en"]')
    .fill('This cycle honours the leading entities of the northern regions.')
  await next(page)
}

function dateGroup(page, name) {
  return page.locator(`.datepicker:has([name="${name}"])`)
}

async function expectSegments(group, values) {
  await expect
    .poll(() =>
      group
        .locator('.datepicker__segment')
        .evaluateAll((inputs) => inputs.map((input) => input.value)),
    )
    .toEqual(values)
}

async function pickSubmissionStart(page, testInfo, year) {
  const group = dateGroup(page, 'submissionStart')
  const button = group.locator('.datepicker__calendar')
  const focusedDay = page.locator('.date-calendar .date-calendar__day:focus')
  await page.locator('input[name="submissionStart"]').fill(`${year}-01-01`)
  await button.click()
  await expect(focusedDay).toHaveAttribute('data-date', `${year}-01-01`)
  await page.keyboard.press('PageDown')
  await page.keyboard.press(say(testInfo, 'ArrowRight', 'ArrowLeft'))
  await page.keyboard.press('ArrowDown')
  await page.keyboard.press('ArrowUp')
  await expect(focusedDay).toHaveAttribute('data-date', `${year}-02-02`)
  await page.keyboard.press('Enter')
  await expect(page.locator('.date-calendar')).toHaveCount(0)
  await expect(button).toBeFocused()
  await expectSegments(group, ['02', '02', String(year)])
}

async function fillDates(page, testInfo) {
  await expectStep(page, testInfo, 2, 'dates')
  const year = new Date().getFullYear() + 2
  await pickSubmissionStart(page, testInfo, year)
  await page.locator('input[name="submissionEnd"]').fill(`${year}-03-15`)
  await page.locator('input[name="committeeStart"]').fill(`${year}-04-01`)
  await page.locator('input[name="committeeEnd"]').fill(`${year}-05-15`)
  await expect(page.locator('.cycle-setup__timeline')).toHaveCount(0)
  await expect(dateHint(page, 'submissionStart')).toContainText(say(testInfo, 'AH', 'هـ'))
  await expect(dateHint(page, 'announcementAt')).toHaveCount(0)
  await next(page)
  await expect(page).toHaveURL(/\/admin\/award\/cycles\/\d+\/setup$/)
}

function dateHint(page, name) {
  return page.locator(`.field:has([name="${name}"]) .field__hint`)
}

async function chooseConditions(page, testInfo) {
  await expectStep(page, testInfo, 3, 'conditions')
  const count = page.locator('.dropdown-select-all__count')
  const toggle = page.locator('.dropdown-select-all__toggle')
  await expect(count).toHaveText(say(testInfo, '13 of 13 regions', '13 من 13 منطقة'))
  await expect(page.locator('[data-testid="cycle-criteria-fact"]')).toHaveCount(0)
  await expect(page.locator('input[name="disqualificationScore"]')).toHaveValue('60.00')
  await toggle.click()
  await expect(count).toHaveText(say(testInfo, '0 of 13 regions', '0 من 13 منطقة'))
  await next(page)
  await expectStep(page, testInfo, 3, 'conditions')
  await toggle.click()
  await expect(count).toHaveText(say(testInfo, '13 of 13 regions', '13 من 13 منطقة'))
  await next(page)
}

async function chooseFee(page, testInfo) {
  await expectStep(page, testInfo, 5, 'fee')
  const paid = page.locator('input[name="entryFeePaid"]')
  await page.locator('[data-testid="cycle-fee"] .toggle-switch__track').click()
  await expect(paid).toBeChecked()
  await expect(page.locator('.pricing-breakdown')).toHaveCount(0)
  await page.locator('input[name="entryFee"]').fill('0')
  await next(page)
  await expect(page.locator('input[name="entryFee"]')).toHaveAttribute('aria-invalid', 'true')
  await expectStep(page, testInfo, 5, 'fee')
  await page.locator('input[name="entryFee"]').fill('450')
  await next(page)
}

async function passSettingSteps(page, testInfo) {
  await expectStep(page, testInfo, 4, 'deadlines')
  await expect(page.locator('input[name="initialReviewSlaDays"]')).toHaveValue('14')
  await next(page)
  await chooseFee(page, testInfo)
  await expectStep(page, testInfo, 6)
  await expect(page.locator('.cycle-staff')).toBeVisible()
  await next(page)
}

async function expectLinearStepper(page, viewport) {
  const stepper = page.locator('.cycle-setup__stepper')
  if (viewport === PHONE) await expect(stepper).toBeHidden()
  else await expect(stepper.locator('.wizard__button').nth(2)).toBeDisabled()
}

async function expectActionsInReach(page, viewport) {
  const box = await page.locator('.cycle-setup__actions').last().boundingBox()
  const limit = viewport === PHONE ? viewport.height + 1 : Number.POSITIVE_INFINITY
  expect(box.y + box.height).toBeLessThanOrEqual(limit)
}

for (const viewport of [DESKTOP, PHONE]) {
  test.describe(`Award cycle setup at ${viewport.width} px`, () => {
    test('creates a cycle through the seven steps and keeps it as a draft', async ({
      page,
    }, testInfo) => {
      await signIn(page, testInfo, viewport)
      await page.goto('/admin/award/cycles/new')
      await expect(page.locator('form[data-step="identity"]')).toBeVisible({
        timeout: LOAD_TIMEOUT,
      })
      await expectLinearStepper(page, viewport)
      await fillIdentity(page, testInfo)
      await fillDates(page, testInfo)
      await chooseConditions(page, testInfo)
      await passSettingSteps(page, testInfo)
      await expectStep(page, testInfo, 7)
      await expect(page.locator('.cycle-summary__section')).toHaveCount(6)
      await expect(page.locator('.cycle-summary__section[data-step="fee"]')).toContainText(
        say(testInfo, 'Paid: SAR 450.00 before VAT', 'مدفوع: 450.00 ر.س قبل الضريبة'),
      )
      await expect(page.locator('.cycle-summary')).toContainText(
        'Moashir Award for the Northern Regions',
      )
      await expect(page.locator('.cycle-summary__row.is-missing')).toHaveCount(1)
      await expect(page.locator('.cycle-actions .btn--primary')).toBeDisabled()
      await expectActionsInReach(page, viewport)
      expect(await pageOverflow(page)).toBe(0)
      await shot(page, `award-cycle-setup-review-${viewport.width}`, testInfo)
      await page.locator('[data-testid="cycle-save-draft"]').click()
      await expect(page).toHaveURL(/\/admin\/award\/cycles$/)
      await expect(page.locator('.table:not(.table--loading) tbody .table__row')).toHaveCount(4)
    })

    test('edits a cycle open for submissions with its locked fields read only', async ({
      page,
    }, testInfo) => {
      await signIn(page, testInfo, viewport)
      await page.goto(`/admin/award/cycles/${RUNNING_CYCLE}`)
      await expect(page).toHaveURL(new RegExp(`/admin/award/cycles/${RUNNING_CYCLE}/setup$`))
      await expect(page.locator('.cycle-summary')).toBeVisible({ timeout: LOAD_TIMEOUT })
      await page.locator('.cycle-summary__section[data-step="dates"] .btn').click()
      await expectStep(page, testInfo, 2, 'dates')
      await expect(
        dateGroup(page, 'submissionStart').locator('.datepicker__segment:disabled'),
      ).toHaveCount(3)
      await expect(
        dateGroup(page, 'submissionStart').locator('.datepicker__calendar'),
      ).toBeDisabled()
      await expect(page.locator('input[name="submissionEnd"]')).toBeEnabled()
      await expect(dateGroup(page, 'submissionEnd').locator('.datepicker__calendar')).toBeEnabled()
      await expect(dateHint(page, 'submissionStart')).toContainText(
        say(testInfo, 'Locked at the current stage', 'مقفل في المرحلة الحالية'),
      )
      await expect(dateHint(page, 'submissionStart')).toContainText(say(testInfo, 'AH', 'هـ'))
      await expect(page.locator('[data-testid="cycle-lock-notes"]')).toContainText(
        say(
          testInfo,
          'Some fields can change only before submissions open.',
          'بعض الحقول لا تُعدَّل إلا قبل فتح التقديم.',
        ),
      )
      await shot(page, `award-cycle-setup-locked-${viewport.width}`, testInfo)
      await next(page)
      await expectStep(page, testInfo, 3, 'conditions')
      await expect(page.locator('.dropdown-tag.is-locked').first()).toBeVisible()
      await page.locator('.cycle-setup__next').click()
      await page.locator('.cycle-setup__next').click()
      await expectStep(page, testInfo, 5, 'fee')
      await expect(page.locator('input[name="entryFeePaid"]')).toBeDisabled()
      await expect(page.locator('[data-testid="cycle-fee-locked"]')).toBeVisible()
      expect(await pageOverflow(page)).toBe(0)
    })
  })
}

test('the public award intro shows the cycle description below the general introduction', async ({
  page,
}, testInfo) => {
  await seedPreferences(page, { locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto('/assessment/advanced')
  const description = page.locator('[data-testid="cycle-description"]')
  await expect(description.locator('.cycle-description__text')).toHaveCount(2)
  await expect(description).toContainText(
    say(testInfo, 'lasting impact for their beneficiaries', 'أثرًا مستدامًا في خدمة المستفيدين'),
  )
})
