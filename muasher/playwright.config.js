import process from 'node:process'
import { defineConfig, devices } from '@playwright/test'

const APP_HOST = 'localhost'

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
    baseURL: process.env.CI ? `http://${APP_HOST}:4173` : `http://${APP_HOST}:5173`,
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
    command: process.env.CI ? 'npm run preview' : 'npm run dev',
    port: process.env.CI ? 4173 : 5173,
    reuseExistingServer: !process.env.CI,
  },
})
