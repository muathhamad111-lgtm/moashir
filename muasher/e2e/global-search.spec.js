import { test, expect } from '@playwright/test'
import { mockSessionCookie, seedSignedIn } from './support/session'

const IGNORED_CONSOLE = /Failed to load resource|50\d|Service Unavailable/i
const SHOTS = 'e2e/__screenshots__/search'

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffix(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function seedSession(page, { roles, locale, theme = 'dark', id = 7001 }) {
  return seedSignedIn(
    page,
    { id, roles, name: { ar: 'نورة بنت سعد', en: 'Noura Saad' } },
    { locale, theme },
  )
}

function trackConsole(page) {
  const errors = []
  page.on('console', (message) => {
    if (message.type() === 'error' && !IGNORED_CONSOLE.test(message.text()))
      errors.push(message.text())
  })
  page.on('pageerror', (error) => errors.push(error.message))
  return errors
}

function combobox(page) {
  return page.getByRole('combobox')
}

function resultGroups(page) {
  return page.getByRole('listbox').getByRole('group')
}

test('follower finds entities and requests grouped, navigates with the keyboard', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedSession(page, { roles: ['request_follower'], locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto('/dashboard')
  const input = combobox(page)
  await expect(input).toBeVisible()

  await page.keyboard.press('Control+KeyK')
  await expect(input).toBeFocused()
  await expect(input).toHaveAttribute('aria-expanded', 'false')

  await input.fill('ا')
  await expect(page.locator('.global-search__state')).toBeVisible()
  await expect(input).toHaveAttribute('aria-expanded', 'true')

  await input.fill(isEn(testInfo) ? 'Ehsan' : 'احسان')
  const listbox = page.getByRole('listbox')
  await expect(resultGroups(page)).toHaveCount(2)
  await expect(listbox.getByRole('option')).toHaveCount(5)
  await expect(page.locator('.global-search__live')).toHaveText(
    isEn(testInfo) ? 'Results found: 5' : 'عدد النتائج: 5',
  )
  await page.screenshot({ path: `${SHOTS}/search-results-dark-${suffix(testInfo)}.png` })

  await input.press('ArrowDown')
  await input.press('ArrowDown')
  const active = listbox.getByRole('option', { selected: true })
  await expect(active).toHaveCount(1)
  await expect(input).toHaveAttribute(
    'aria-activedescendant',
    (await active.getAttribute('id')) ?? '',
  )
  await input.press('Enter')
  await expect(page).toHaveURL(/\/admin\/requests\/9108$/)
  await expect(input).toHaveAttribute('aria-expanded', 'false')
  expect(errors).toEqual([])
})

test('selecting an entity opens the entity record without exposing its name in the URL', async ({
  page,
}, testInfo) => {
  await seedSession(page, {
    roles: ['request_follower'],
    locale: isEn(testInfo) ? 'en' : 'ar',
    theme: 'light',
  })
  await page.goto('/dashboard')
  const input = combobox(page)
  await input.click()
  await input.fill(isEn(testInfo) ? 'Namaa' : 'نماء')
  const entity = resultGroups(page).first().getByRole('option').first()
  await expect(entity).toBeVisible()
  await page.screenshot({ path: `${SHOTS}/search-results-light-${suffix(testInfo)}.png` })
  await entity.click()
  await expect(page).toHaveURL(/\/admin\/entities\/6001$/)
  await expect(page.locator('.entity-record__title, h1').first()).toContainText(
    isEn(testInfo) ? 'Namaa Development Foundation' : 'مؤسسة نماء للتنمية',
  )
})

test('recent searches are kept per user and offered on focus', async ({ page }, testInfo) => {
  await seedSession(page, { roles: ['request_follower'], locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto('/dashboard')
  const input = combobox(page)
  await input.click()
  await input.fill('SLF-2026')
  await page.getByRole('option').first().click()
  await expect(page).toHaveURL(/\/admin\/assessments\/9002$/)
  await input.click()
  await expect(resultGroups(page)).toHaveCount(1)
  await expect(page.getByRole('option')).toHaveText(['SLF-2026'])
  await page.screenshot({ path: `${SHOTS}/search-recent-${suffix(testInfo)}.png` })
  await page.locator('.global-search__footer .btn').click()
  await expect(input).toHaveAttribute('aria-expanded', 'false')
})

for (const theme of ['dark', 'light']) {
  test(`the System Administrator sees users with the e-mail under the name (${theme})`, async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await seedSession(page, {
      roles: ['system_admin'],
      locale: isEn(testInfo) ? 'en' : 'ar',
      theme,
    })
    await page.goto('/dashboard')
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme)
    const input = combobox(page)
    await input.click()
    await input.fill('القحطاني')
    await expect(resultGroups(page)).toHaveCount(1)
    await expect(resultGroups(page).first()).toContainText(isEn(testInfo) ? 'Users' : 'المستخدمون')

    const user = page.getByRole('option').first()
    const name = user.locator('.global-search__option-title')
    const email = user.locator('.global-search__option-email')
    await expect(name).toHaveText('عبدالله بن سعد القحطاني')
    await expect(email).toHaveText('abdullah.alqahtani@moashir.example.sa')
    await expect(email).toHaveAttribute('dir', 'ltr')
    await expect(user).toHaveAccessibleName(
      isEn(testInfo)
        ? 'عبدالله بن سعد القحطاني, email abdullah.alqahtani@moashir.example.sa, System Administrator'
        : 'عبدالله بن سعد القحطاني، البريد الإلكتروني abdullah.alqahtani@moashir.example.sa، مدير النظام',
    )
    const [nameBox, emailBox] = [await name.boundingBox(), await email.boundingBox()]
    expect(emailBox.y).toBeGreaterThanOrEqual(nameBox.y + nameBox.height - 1)
    await page.screenshot({ path: `${SHOTS}/search-users-${theme}-${suffix(testInfo)}.png` })
    expect(errors).toEqual([])
  })
}

test('empty and error states with retry; Escape closes then clears', async ({ page }, testInfo) => {
  await seedSession(page, { roles: ['request_follower'], locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto('/dashboard')
  const input = combobox(page)
  await input.click()
  await input.fill('zzzz')
  await expect(page.locator('.global-search__state')).toContainText('zzzz')

  await page.context().addCookies([mockSessionCookie({ id: 7001, roles: [] })])
  await input.fill('سقيا')
  await expect(page.locator('.global-search__state--error')).toBeVisible()
  await page.screenshot({ path: `${SHOTS}/search-error-${suffix(testInfo)}.png` })
  await page.context().addCookies([mockSessionCookie({ id: 7001, roles: ['request_follower'] })])
  await page.locator('.global-search__state--error .btn').click()
  await expect(page.getByRole('option')).not.toHaveCount(0)

  await input.press('Escape')
  await expect(input).toHaveAttribute('aria-expanded', 'false')
  await input.press('Escape')
  await expect(input).toHaveValue('')
})

test('the search fits the smallest supported width', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 360, height: 740 })
  await seedSession(page, { roles: ['request_follower'], locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto('/dashboard')
  const input = combobox(page)
  await input.click()
  await input.fill(isEn(testInfo) ? 'Society' : 'جمعية')
  await expect(page.getByRole('option').first()).toBeVisible()
  const boxes = await page.evaluate(() =>
    ['.global-search__field', '.global-search__popup'].map((selector) => {
      const box = document.querySelector(selector).getBoundingClientRect()
      return { left: box.left, right: box.right }
    }),
  )
  boxes.forEach((box) => {
    expect(box.left).toBeGreaterThanOrEqual(0)
    expect(box.right).toBeLessThanOrEqual(360)
  })
  await page.screenshot({ path: `${SHOTS}/search-mobile-${suffix(testInfo)}.png` })
})
