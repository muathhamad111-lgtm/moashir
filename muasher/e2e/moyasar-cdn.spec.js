import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import config from '../playwright.config.js'
import { seedSignedIn } from './support/session'

const SHOTS = 'e2e/__screenshots__/signin'
const ENTITY = { id: 5001, roles: ['entity'], entityId: 5001 }
const FORM_SCRIPT = 'https://cdn.moyasar.com/mpf/1.15.0/moyasar.js'
const FORM_STYLESHEET = 'https://cdn.moyasar.com/mpf/1.15.0/moyasar.css'
const CDN_FORM_COOKIE = { name: 'moashir_moyasar_form', value: 'cdn', url: config.use.baseURL }
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'

mkdirSync(SHOTS, { recursive: true })

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

test('the payment page renders the real Moyasar form from the script the checkout names', async ({
  page,
}, testInfo) => {
  await page.context().addCookies([CDN_FORM_COOKIE])
  await seedSignedIn(page, ENTITY, { locale: isEn(testInfo) ? 'en' : 'ar' })
  const answers = []
  page.on('response', (response) => {
    if (response.url().startsWith('https://cdn.moyasar.com/'))
      answers.push(`${response.status()} ${response.url()}`)
  })
  await page.goto('/assessment/accredited/pay')
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await page.locator('.checkout__start .btn--primary').click()
  const host = page.locator('.moyasar-host__form')
  await expect(host).toBeVisible({ timeout: 20000 })
  await expect.poll(() => page.evaluate(() => typeof window.Moyasar?.init)).toBe('function')
  await expect(host.locator('input').first()).toBeVisible()
  await expect(page.locator('[data-fake-moyasar="form"]')).toHaveCount(0)
  await expect(page.locator('.moyasar-host .error-state')).toHaveCount(0)
  expect(answers).toEqual(expect.arrayContaining([`200 ${FORM_SCRIPT}`, `200 ${FORM_STYLESHEET}`]))
  expect(await page.locator('script[data-moyasar-form]').getAttribute('integrity')).toBeNull()
  await page.screenshot({
    path: `${SHOTS}/moyasar-cdn-form-${isEn(testInfo) ? 'en-ltr' : 'ar-rtl'}.png`,
    fullPage: true,
  })
})
