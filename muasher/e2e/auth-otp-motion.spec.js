import { test, expect } from '@playwright/test'
import { seedPreferences } from './support/session'
import {
  DEV_OTP_CODE,
  requestSignInCode,
  signInTab,
  submitSignIn,
  typeCode,
} from './support/signIn'

const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const ENTITY_EMAIL = 'info@albirr-riyadh.example.sa'
const STAFF_ENROLLED = 'yasser.alghamdi@moashir.example.sa'
const AHEAD = { ar: -1, en: 1 }

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

async function recordMotion(page) {
  await page.addInitScript(() => {
    window.__authMotion = []
    const animate = Element.prototype.animate
    Element.prototype.animate = function recordAnimate(keyframes, options) {
      const level = this.matches?.('.auth-layout__page > *')
        ? 'page'
        : this.matches?.('.sign-in-methods__panel > *') && 'panel'
      if (level) {
        const shifts = keyframes.map((frame) => parseFloat(frame.translate) || 0)
        window.__authMotion.push({
          level,
          opacity: keyframes.map((frame) => frame.opacity),
          shifts,
        })
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
  await page.evaluate(() => {
    window.__brandPanel = document.querySelector('aside.auth-layout__brand')
  })
}

function cardBox(page) {
  return page
    .locator('.auth-layout__card')
    .evaluate((card) => [card.getBoundingClientRect().x, card.getBoundingClientRect().y])
}

async function settled(page) {
  await expect(page.locator('.auth-layout__page > *')).toHaveCSS('opacity', '1')
  await expect
    .poll(() =>
      page
        .locator('.auth-layout__card')
        .evaluate(
          (card) =>
            card
              .getAnimations({ subtree: true })
              .filter(
                (motion) => !(motion instanceof CSSTransition || motion instanceof CSSAnimation),
              ).length,
        ),
    )
    .toBe(0)
}

async function expectPillUnder(page, method) {
  const tabBox = await signInTab(page, method).boundingBox()
  await expect
    .poll(async () => {
      const pill = await page.locator('.sign-in-methods__indicator').boundingBox()
      return Math.abs(pill.x - tabBox.x) + Math.abs(pill.width - tabBox.width)
    })
    .toBeLessThan(2)
}

async function expectSameBrandPanel(page) {
  expect(
    await page.evaluate(
      () => document.querySelector('aside.auth-layout__brand') === window.__brandPanel,
    ),
  ).toBe(true)
}

async function expectNoSidewaysScroll(page) {
  expect(
    await page.evaluate(() => document.scrollingElement.scrollWidth <= window.innerWidth),
  ).toBe(true)
}

async function codeStep(page) {
  await requestSignInCode(page, ENTITY_EMAIL)
  await expect(page).toHaveURL(/\/auth\/otp/)
  await expect(page.locator('.otp-input__box').first()).toBeFocused()
  await settled(page)
}

async function backToPassword(page) {
  await page.locator('.auth-otp__password').focus()
  await page.keyboard.press('Enter')
  await expect(page).toHaveURL(/\/auth\/login/)
  await expect(page.locator('[name="password"]')).toBeFocused()
  await settled(page)
}

for (const [label, viewport] of [
  ['desktop', DESKTOP],
  ['phone', MOBILE],
]) {
  test.describe(`auth pages — password and verification code (${label})`, () => {
    test.use({ viewport })

    test('the method tabs slide the form along the reading direction, keeping focus on the tab', async ({
      page,
    }, testInfo) => {
      const ahead = AHEAD[localeOf(testInfo)]
      await recordMotion(page)
      await openLogin(page, testInfo)
      await page.locator('[name="identifier"]').fill(ENTITY_EMAIL)
      const before = await cardBox(page)

      await signInTab(page, 'password').focus()
      await page.keyboard.press(ahead === 1 ? 'ArrowRight' : 'ArrowLeft')
      await expect(signInTab(page, 'code')).toBeFocused()
      await expect(page.locator('[name="password"]')).toHaveCount(0)
      await settled(page)
      await expectPillUnder(page, 'code')
      const [leave, enter] = await motionOf(page)
      expect(leave).toMatchObject({ level: 'panel', opacity: [1, 0.2] })
      expect(Math.sign(leave.shifts[1])).toBe(-ahead)
      expect(enter).toMatchObject({ level: 'panel', opacity: [0.2, 1] })
      expect(Math.sign(enter.shifts[0])).toBe(ahead)

      await signInTab(page, 'password').click()
      await expect(page.locator('[name="password"]')).toBeVisible()
      await settled(page)
      await expectPillUnder(page, 'password')
      const [, back] = await motionOf(page)
      expect(Math.sign(back.shifts[0])).toBe(-ahead)
      await expect(page.locator('[name="identifier"]')).toHaveValue(ENTITY_EMAIL)
      expect(await cardBox(page)).toEqual(before)
      await expectNoSidewaysScroll(page)
    })

    test('the code step slides in ahead and the password step slides back, focus following', async ({
      page,
    }, testInfo) => {
      const ahead = AHEAD[localeOf(testInfo)]
      await recordMotion(page)
      await openLogin(page, testInfo)

      await codeStep(page)
      const toCode = (await motionOf(page)).filter((entry) => entry.level === 'page')
      expect(toCode).toHaveLength(2)
      expect(Math.sign(toCode[1].shifts[0])).toBe(ahead)
      await expect(page.locator('.auth-otp__cooldown')).toBeVisible()
      await expect(page.locator('.auth-otp__expiry')).toBeVisible()
      await expectSameBrandPanel(page)
      await expectNoSidewaysScroll(page)

      await backToPassword(page)
      const toPassword = (await motionOf(page)).filter((entry) => entry.level === 'page')
      expect(toPassword).toHaveLength(2)
      expect(Math.sign(toPassword[1].shifts[0])).toBe(-ahead)
      await expect(signInTab(page, 'password')).toHaveAttribute('aria-selected', 'true')
      await expect(page.locator('[name="identifier"]')).toHaveValue(ENTITY_EMAIL)
      await expectSameBrandPanel(page)
    })
  })
}

test.describe('auth pages — the verification code step', () => {
  test.use({ viewport: DESKTOP })

  test('boxes arrive in sequence, the ring drains, a wrong code shakes and a right one shows the tick', async ({
    page,
  }, testInfo) => {
    await openLogin(page, testInfo)
    await codeStep(page)
    const boxes = page.locator('.otp-input__box')
    await expect(boxes.first()).toHaveCSS('animation-name', 'otp-input-box-in')
    await expect(boxes.nth(1)).toHaveCSS('animation-delay', '0.05s')

    const ring = page.locator('.auth-otp__cooldown .otp-resend-ring__fill')
    const startOffset = Number(await ring.getAttribute('stroke-dashoffset'))
    await expect
      .poll(async () => Number(await ring.getAttribute('stroke-dashoffset')))
      .toBeGreaterThan(startOffset)

    await page.evaluate(() => {
      window.__otpShakes = 0
      const group = document.querySelector('.otp-input')
      new MutationObserver(() => {
        if (group.classList.contains('is-shaking')) window.__otpShakes += 1
      }).observe(group, { attributes: true, attributeFilter: ['class'] })
    })
    await typeCode(page, '111111')
    await expect(page.locator('.auth-otp__error')).toBeVisible()
    await expect.poll(() => page.evaluate(() => window.__otpShakes)).toBeGreaterThan(0)
    await expect(page.locator('.otp-input')).not.toHaveClass(/is-shaking/)

    await typeCode(page, DEV_OTP_CODE)
    await expect(page.locator('.auth-otp__verified')).toBeVisible()
    await expect(page.locator('.auth-otp__tick')).toBeVisible()
    await expect(boxes.first()).toHaveCSS('opacity', '1')
    await expect(page.locator('.otp-input')).toHaveClass(/is-verified/)
    await expect(page.locator('.portal-top-bar')).toBeVisible()
  })
})

test.describe('auth pages — password and verification code motion settings', () => {
  test.use({ viewport: DESKTOP })

  test('staff sign-in slides ahead to the authenticator code with its first box focused', async ({
    page,
  }, testInfo) => {
    await recordMotion(page)
    await openLogin(page, testInfo)
    await submitSignIn(page, STAFF_ENROLLED)
    await expect(page).toHaveURL(/\/auth\/mfa/)
    await expect(page.locator('.otp-input__box').first()).toBeFocused()
    await settled(page)
    const toMfa = (await motionOf(page)).filter((entry) => entry.level === 'page')
    expect(Math.sign(toMfa[1].shifts[0])).toBe(AHEAD[localeOf(testInfo)])
    await expectSameBrandPanel(page)
  })

  test('reduced motion swaps at once and ends in the same state', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await recordMotion(page)
    await openLogin(page, testInfo)

    await expect(page.locator('.sign-in-methods__indicator')).toHaveCSS('transition-duration', '0s')
    await signInTab(page, 'code').click()
    await expect(page.locator('[name="password"]')).toHaveCount(0)
    await expectPillUnder(page, 'code')
    await codeStep(page)
    await backToPassword(page)
    await expect(page.locator('[name="identifier"]')).toHaveValue(ENTITY_EMAIL)
    await expectSameBrandPanel(page)
    expect(await motionOf(page)).toEqual([])
    expect(
      await page.locator('.auth-layout__viewport').evaluate((node) => node.getAnimations().length),
    ).toBe(0)
  })
})
