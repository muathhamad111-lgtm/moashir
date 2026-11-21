import { test, expect } from '@playwright/test'
import { seedPreferences } from './support/session'
import { MOCK_PASSWORD, openSignIn } from './support/signIn'

const LABELS = {
  ar: { show: 'إظهار كلمة المرور', hide: 'إخفاء كلمة المرور' },
  en: { show: 'Show password', hide: 'Hide password' },
}

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function boxesOf(page) {
  return page.evaluate(() => {
    const input = document.querySelector('[name="password"]')
    const button = input.parentElement.querySelector('.password-reveal')
    const inputBox = input.getBoundingClientRect()
    const buttonBox = button.getBoundingClientRect()
    const rtl = document.documentElement.dir === 'rtl'
    return {
      dir: document.documentElement.dir,
      endGap: rtl ? buttonBox.left - inputBox.left : inputBox.right - buttonBox.right,
      width: buttonBox.width,
      height: buttonBox.height,
      paddingEnd: parseFloat(getComputedStyle(input).paddingInlineEnd),
    }
  })
}

test('the sign-in password can be shown and hidden again with the reveal button', async ({
  page,
}, testInfo) => {
  const locale = localeOf(testInfo)
  await seedPreferences(page, { locale })
  await openSignIn(page)

  const password = page.locator('[name="password"]')
  const reveal = page.getByRole('button', { name: LABELS[locale].show })
  await password.fill(MOCK_PASSWORD)
  await expect(password).toHaveAttribute('type', 'password')
  await expect(reveal).toHaveAttribute('type', 'button')
  await expect(reveal).toHaveAttribute('aria-pressed', 'false')
  await expect(reveal).toHaveAttribute('aria-controls', (await password.getAttribute('id')) ?? '')

  await reveal.click()
  await expect(password).toHaveAttribute('type', 'text')
  await expect(password).toHaveValue(MOCK_PASSWORD)
  await expect(password).toHaveAttribute('autocomplete', 'current-password')
  await expect(password).toBeFocused()
  await expect(page).toHaveURL(/\/auth\/login/)

  const hide = page.getByRole('button', { name: LABELS[locale].hide })
  await expect(hide).toHaveAttribute('aria-pressed', 'true')
  await hide.focus()
  await page.keyboard.press('Space')
  await expect(password).toHaveAttribute('type', 'password')
  await expect(password).toHaveValue(MOCK_PASSWORD)
  await expect(page.getByRole('button', { name: LABELS[locale].show })).toBeFocused()

  const boxes = await boxesOf(page)
  expect(boxes.dir).toBe(locale === 'ar' ? 'rtl' : 'ltr')
  expect(boxes.width).toBeGreaterThanOrEqual(24)
  expect(boxes.height).toBeGreaterThanOrEqual(24)
  expect(boxes.paddingEnd).toBeGreaterThan(boxes.width)
  expect(boxes.endGap).toBeGreaterThanOrEqual(0)
  expect(boxes.endGap + boxes.width).toBeLessThanOrEqual(boxes.paddingEnd)
})
