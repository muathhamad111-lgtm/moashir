import { randomUUID } from 'node:crypto'
import { test, expect } from '@playwright/test'
import { assertMailLogsConfigured } from '../integration/support/mailbox'
import { registerEntity } from '../integration/support/entities'

const SHOTS = 'e2e/__screenshots__/37d/integration'
const RUN = Date.now().toString(36)
const ENTITY = {
  email: `posture.${RUN}@alamal-charity.example.sa`,
  password: `Amal-${randomUUID()}`,
  nameAr: 'جمعية الأمل الخيرية',
  nameEn: 'Al-Amal Charity',
  person: 'سارة بنت محمد القرني',
}

test.describe('real-operation build (SEC-07)', () => {
  test('ships no mock service worker and registers none', async ({ page, request }) => {
    const worker = await request.get('/mockServiceWorker.js')
    expect(await worker.text()).not.toContain('Mock Service Worker')
    await page.goto('/auth/login')
    await expect(page.locator('main').first()).toBeVisible()
    const registrations = await page.evaluate(async () =>
      (await navigator.serviceWorker.getRegistrations()).map(
        (item) => item.active?.scriptURL ?? '',
      ),
    )
    expect(registrations).toEqual([])
  })

  test('the demo-only screens are static coming-soon screens on the real backend', async ({
    page,
  }) => {
    test.setTimeout(180000)
    assertMailLogsConfigured()
    await registerEntity(page, ENTITY)
    for (const path of ['/bag', '/bag/comparison']) {
      await page.goto(path)
      await expect(page.locator('.coming-soon')).toBeVisible()
      await expect(page.locator('.coming-soon__badge')).toHaveText('قريبًا')
    }
    await page.screenshot({ path: `${SHOTS}/coming-soon-real-build.png`, fullPage: true })
  })
})
