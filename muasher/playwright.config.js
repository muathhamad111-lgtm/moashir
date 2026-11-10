import process from 'node:process'
import { defineConfig, devices } from '@playwright/test'

const APP_HOST = 'localhost'
const DEV_PORT = Number(process.env.PLAYWRIGHT_PORT ?? 5173)
const LEVEL_ACHIEVED_SINCE = process.env.VITE_LEVEL_ACHIEVED_SINCE || '2026-11-06'

const MOCK_FAULTS_OFF = {
  cookies: [
    {
      name: 'moashir_mock_faults',
      value: 'off',
      domain: APP_HOST,
      path: '/',
      expires: -1,
      httpOnly: false,
      secure: false,
      sameSite: 'Lax',
    },
  ],
  origins: [],
}

export default defineConfig({
  testDir: './e2e',
  testIgnore: [
    '**/integration/**',
    '**/posture/**',
    ...(process.env.MOASHIR_CHECK_MOYASAR_CDN ? [] : ['**/moyasar-cdn.spec.js']),
    ...(process.env.MOASHIR_BANK_TRANSFER_SCREENS ? [] : ['**/bank-transfer-screens.spec.js']),
    ...(process.env.MOASHIR_DIALOG_SCREENS ? [] : ['**/dialog-screens.spec.js']),
    // CI tests the production build, which has no dev-only component gallery route.
    ...(process.env.CI ? ['**/gallery.spec.js'] : []),
  ],
  timeout: 30 * 1000,
  expect: {
    timeout: 5000,
  },
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [['html', { open: 'never' }]],
  use: {
    actionTimeout: 0,
    baseURL: process.env.CI ? `http://${APP_HOST}:4173` : `http://${APP_HOST}:${DEV_PORT}`,
    storageState: MOCK_FAULTS_OFF,
    trace: 'on-first-retry',
    headless: true,
  },
  projects: [
    {
      name: 'chromium-ar-rtl',
      use: {
        ...devices['Desktop Chrome'],
        locale: 'ar-SA',
      },
    },
    {
      name: 'chromium-en-ltr',
      use: {
        ...devices['Desktop Chrome'],
        locale: 'en-US',
      },
    },
  ],
  webServer: {
    command: process.env.CI ? 'npm run preview' : `npm run dev -- --port ${DEV_PORT} --strictPort`,
    port: process.env.CI ? 4173 : DEV_PORT,
    reuseExistingServer: !process.env.CI,
    env: { VITE_LEVEL_ACHIEVED_SINCE: LEVEL_ACHIEVED_SINCE },
  },
})
