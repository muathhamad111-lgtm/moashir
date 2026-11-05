import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedPreferences } from './support/session'
import { settleFonts } from './support/screenshots'

const SHOTS = 'e2e/__screenshots__/mobile-motion'
const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  mobile: { width: 390, height: 844 },
}
const TABLET_PORTRAIT = { width: 768, height: 1024 }
const THEMES = ['light', 'dark']
const FULL_SCORE = 100
const REVEALED_FOR_SCREENSHOTS = [
  '.hero-preview',
  '#tour .home-section__head',
  '.home-tour__steps',
  '.home-tour__device',
]
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'

mkdirSync(SHOTS, { recursive: true })

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function suffixOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en-ltr' : 'ar-rtl'
}

async function openHome(page, testInfo, theme = 'light') {
  await seedPreferences(page, { locale: localeOf(testInfo), theme })
  await page.goto('/')
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await expect(page.locator('.home-hero__title')).toBeVisible({ timeout: 10000 })
}

async function tourStepMs(page) {
  const duration = await page.evaluate(() =>
    getComputedStyle(document.documentElement).getPropertyValue('--motion-tour-step'),
  )
  return Number.parseFloat(duration) * 1000
}

async function revealAll(page) {
  for (const selector of REVEALED_FOR_SCREENSHOTS) {
    const target = page.locator(selector)
    await target.scrollIntoViewIfNeeded()
    await expect(target).toHaveClass(/is-revealed/)
  }
  await settleScore(page)
  await page.evaluate(() => window.scrollTo(0, 0))
}

async function settleScore(page) {
  const ring = page.locator('.hero-preview .score-ring')
  const offset = Number(await ring.locator('.score-ring__value').getAttribute('stroke-dashoffset'))
  await expect(ring.locator('.score-ring__number')).toHaveText(`${FULL_SCORE - offset}%`)
}

function motionControl(page) {
  return page.locator('.home-hero__motion')
}

function tourControl(page) {
  return page.locator('.home-tour__toggle')
}

function tourStep(page, index) {
  return page.locator('.home-tour__step').nth(index)
}

test.describe('phones — no motion controls, the motion keeps running', () => {
  test.use({ viewport: VIEWPORTS.mobile })

  test('the hero and the tour render no pause / play control', async ({ page }, testInfo) => {
    await openHome(page, testInfo)
    await expect(motionControl(page)).toHaveCount(0)
    await expect(tourControl(page)).toHaveCount(0)
    const preview = page.locator('.hero-preview')
    await preview.scrollIntoViewIfNeeded()
    await expect(preview).toHaveClass(/is-revealed/)
    const card = page.locator('.hero-preview__card')
    await expect(card).toHaveCSS('animation-name', 'hero-float')
    await expect(card).toHaveCSS('animation-play-state', 'running')
  })

  test('the tour still advances by itself', async ({ page }, testInfo) => {
    await page.clock.install()
    await openHome(page, testInfo)
    await page.locator('#tour').scrollIntoViewIfNeeded()
    await expect(page.locator('#tour')).toHaveClass(/is-running/)
    await expect(tourStep(page, 0)).toHaveAttribute('aria-current', 'step')
    await page.clock.runFor(await tourStepMs(page))
    await expect(tourStep(page, 1)).toHaveAttribute('aria-current', 'step')
    await expect(tourControl(page)).toHaveCount(0)
  })

  test('reduced motion keeps the page still, as before', async ({ page }, testInfo) => {
    await page.clock.install()
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await openHome(page, testInfo)
    await expect(motionControl(page)).toHaveCount(0)
    await expect(tourControl(page)).toHaveCount(0)
    await expect(page.locator('.hero-preview__card')).toHaveCSS('animation-name', 'none')
    await page.locator('#tour').scrollIntoViewIfNeeded()
    await page.clock.runFor((await tourStepMs(page)) * 2)
    await expect(page.locator('#tour')).not.toHaveClass(/is-running/)
    await expect(tourStep(page, 0)).toHaveAttribute('aria-current', 'step')
  })
})

test.describe('desktop — the controls stay', () => {
  test.use({ viewport: VIEWPORTS.desktop })

  test('the hero and the tour keep their pause / play controls', async ({ page }, testInfo) => {
    await openHome(page, testInfo)
    await expect(motionControl(page)).toBeVisible()
    await expect(motionControl(page)).toHaveAttribute('aria-pressed', 'false')
    await page.locator('#tour').scrollIntoViewIfNeeded()
    await expect(tourControl(page)).toBeVisible()
    await expect(tourControl(page)).toHaveAttribute('aria-pressed', 'true')
  })

  test('reduced motion keeps the current behaviour', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await openHome(page, testInfo)
    await expect(motionControl(page)).toBeHidden()
    await page.locator('#tour').scrollIntoViewIfNeeded()
    await expect(tourControl(page)).toBeVisible()
    await expect(tourControl(page)).toHaveAttribute('aria-pressed', 'false')
  })

  test('resizing across the tablet breakpoint updates the controls without a reload', async ({
    page,
  }, testInfo) => {
    await openHome(page, testInfo)
    await page.evaluate(() => {
      window.moashirSameDocument = true
    })
    await page.setViewportSize(VIEWPORTS.mobile)
    await expect(motionControl(page)).toHaveCount(0)
    await expect(tourControl(page)).toHaveCount(0)
    await page.setViewportSize(TABLET_PORTRAIT)
    await expect(motionControl(page)).toHaveCount(1)
    await expect(tourControl(page)).toHaveCount(1)
    expect(await page.evaluate(() => window.moashirSameDocument)).toBe(true)
  })
})

for (const theme of THEMES) {
  for (const [viewportName, viewport] of Object.entries(VIEWPORTS)) {
    test.describe(`motion controls screenshots — ${theme} ${viewportName}`, () => {
      test.use({ viewport })

      test(`captures the hero and the tour (${theme}, ${viewportName})`, async ({
        page,
      }, testInfo) => {
        const name = `${theme}-${viewportName}-${suffixOf(testInfo)}`
        await openHome(page, testInfo, theme)
        await expect(page.locator('html')).toHaveAttribute('data-theme', theme)
        await settleFonts(page)
        await revealAll(page)
        await page
          .locator('.home-hero')
          .screenshot({ path: `${SHOTS}/hero-${name}.png`, animations: 'disabled' })
        await page.screenshot({
          path: `${SHOTS}/tour-${name}.png`,
          fullPage: true,
          clip: await page.locator('#tour').boundingBox(),
          animations: 'disabled',
        })
      })
    })
  }
}
