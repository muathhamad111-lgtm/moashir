import { test, expect } from '@playwright/test'
import { seedPreferences } from './support/session'

const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const TITLES = {
  ar: { login: 'تسجيل الدخول', register: 'تسجيل جهة جديدة', forgot: 'نسيت كلمة المرور' },
  en: { login: 'Log in', register: 'Register a new entity', forgot: 'Forgot password' },
}

const AHEAD = { ar: -1, en: 1 }

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

async function recordPageMotion(page) {
  await page.addInitScript(() => {
    window.__authMotion = []
    const animate = Element.prototype.animate
    Element.prototype.animate = function recordAnimate(keyframes, options) {
      if (this.matches?.('.auth-layout__page > *')) {
        const shifts = keyframes.map((frame) => parseFloat(frame.translate) || 0)
        window.__authMotion.push({ opacity: keyframes.map((frame) => frame.opacity), shifts })
      }
      return animate.call(this, keyframes, options)
    }
  })
}

function motionOf(page) {
  return page.evaluate(() => window.__authMotion.splice(0))
}

async function openLogin(page, testInfo) {
  await seedPreferences(page, { locale: localeOf(testInfo), theme: 'light' })
  await page.goto('/auth/login')
  await expect(page.locator('[name="identifier"]')).toBeVisible({ timeout: 10000 })
}

async function switchByKeyboard(page, link, title) {
  await link.focus()
  await page.keyboard.press('Enter')
  const heading = page.locator('.auth-layout__page h1')
  await expect(heading).toHaveText(title)
  await expect(heading).toBeFocused()
  await expect(page.locator('.auth-layout__page > *')).toHaveCSS('opacity', '1')
}

test.describe('auth pages — switching between sign-in, register and forgot password', () => {
  test.use({ viewport: DESKTOP })

  test('slides forward and back along the reading direction, keeping keyboard focus', async ({
    page,
  }, testInfo) => {
    const titles = TITLES[localeOf(testInfo)]
    const ahead = AHEAD[localeOf(testInfo)]
    await recordPageMotion(page)
    await openLogin(page, testInfo)

    await switchByKeyboard(page, page.locator('.auth-form__switch a'), titles.register)
    const [leave, enter] = await motionOf(page)
    expect(leave.opacity).toEqual([1, 0])
    expect(Math.sign(leave.shifts[1])).toBe(-ahead)
    expect(enter.opacity).toEqual([0, 1])
    expect(Math.sign(enter.shifts[0])).toBe(ahead)

    await switchByKeyboard(page, page.locator('.auth-form__switch a'), titles.login)
    const [, back] = await motionOf(page)
    expect(Math.sign(back.shifts[0])).toBe(-ahead)

    await switchByKeyboard(page, page.locator('.auth-form__forgot'), titles.forgot)
    expect(await motionOf(page)).toHaveLength(2)
    await page.keyboard.press('Tab')
    await expect(page.locator('[name="email"]')).toBeFocused()

    await switchByKeyboard(page, page.locator('.auth-form__switch a'), titles.login)
    await page.goBack()
    await expect(page.locator('.auth-layout__page h1')).toHaveText(titles.forgot)
  })

  test('switches without any motion for reduced motion', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await recordPageMotion(page)
    await openLogin(page, testInfo)
    await switchByKeyboard(
      page,
      page.locator('.auth-form__switch a'),
      TITLES[localeOf(testInfo)].register,
    )
    await expect(page.locator('.auth-layout__card form')).toBeVisible()
    expect(await motionOf(page)).toEqual([])
    expect(
      await page.locator('.auth-layout__viewport').evaluate((node) => node.getAnimations().length),
    ).toBe(0)
  })

  test('prefetches the register data while the pointer rests on the link', async ({
    page,
  }, testInfo) => {
    await openLogin(page, testInfo)
    const terms = page.waitForRequest('**/api/v1/terms/current')
    await page.locator('.auth-form__switch a').hover()
    await terms
    const extraReads = []
    page.on('request', (request) => {
      if (/\/api\/v1\/(terms\/current|regions|auth\/register\/options)$/.test(request.url()))
        extraReads.push(request.url())
    })
    await page.locator('.auth-form__switch a').click()
    await expect(page.locator('.auth-layout__card form')).toBeVisible()
    await expect(page.locator('.entity-register__loading')).toHaveCount(0)
    expect(extraReads).toEqual([])
  })
})

test.describe('auth pages — brand panel dot field', () => {
  test('the dots and the pattern follow the pointer on the split layout', async ({
    page,
  }, testInfo) => {
    await page.setViewportSize(DESKTOP)
    await openLogin(page, testInfo)
    const aside = page.locator('aside.auth-layout__brand')
    await expect(aside.locator('canvas.auth-layout__dots')).toHaveAttribute('aria-hidden', 'true')
    const box = await aside.boundingBox()
    await page.mouse.move(box.x + box.width * 0.15, box.y + box.height * 0.85)
    await page.mouse.move(box.x + box.width * 0.2, box.y + box.height * 0.8, { steps: 6 })
    await expect
      .poll(() =>
        aside
          .locator('.auth-layout__pattern')
          .evaluate((node) => Math.abs(parseFloat(node.style.getPropertyValue('--px')) || 0)),
      )
      .toBeGreaterThan(1)
  })

  test('the stacked phone layout draws no dot field', async ({ page }, testInfo) => {
    await page.setViewportSize(MOBILE)
    await openLogin(page, testInfo)
    await expect(page.locator('aside.auth-layout__brand .auth-layout__logo')).toBeVisible()
    await expect(page.locator('canvas.auth-layout__dots')).toHaveCount(0)
  })
})
