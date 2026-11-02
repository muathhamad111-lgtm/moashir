import process from 'node:process'
import { loadEnv } from 'vite'
import { test, expect } from '@playwright/test'
import { isFontAwesomeProInstalled } from '../vite-plugins/fontAwesome'

const KIT_HOST = 'kit.fontawesome.com'
const kitConfigured = Boolean(loadEnv('development', process.cwd(), 'VITE_').VITE_FONTAWESOME_KIT_ID)

function trackKit(page) {
  const kitRequests = []
  page.on('request', (request) => {
    if (new URL(request.url()).host === KIT_HOST) kitRequests.push(request.url())
  })
  return kitRequests
}

if (isFontAwesomeProInstalled()) {
  test('icons render from the bundled Pro webfonts without any request to the kit CDN', async ({ page }) => {
    const kitRequests = trackKit(page)
    const consoleErrors = []
    page.on('console', (message) => {
      if (message.type() === 'error' && /font awesome/i.test(message.text())) consoleErrors.push(message.text())
    })
    await page.goto('/')
    const icon = page.locator('i.fa-solid').first()
    await expect(icon).toBeAttached()
    await page.evaluate(() => document.fonts.ready)
    const fontState = await icon.evaluate((element) => ({
      family: getComputedStyle(element).fontFamily,
      loaded: [...document.fonts].some(
        (face) => face.family.replaceAll('"', '').startsWith('Font Awesome') && face.status === 'loaded',
      ),
    }))
    expect(fontState.family).toMatch(/Font Awesome/)
    expect(fontState.loaded).toBe(true)
    await expect(page.locator(`script[src*="${KIT_HOST}"]`)).toHaveCount(0)
    expect(kitRequests).toEqual([])
    expect(consoleErrors).toEqual([])
  })
} else {
  test('without the Pro package the kit stays as the working fallback', async ({ page }) => {
    await page.goto('/')
    await expect(page.locator('i.fa-solid').first()).toBeAttached()
    await expect(page.locator(`script[src*="${KIT_HOST}"]`)).toHaveCount(kitConfigured ? 1 : 0)
  })
}
