import process from 'node:process'
import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './e2e/integration',
  timeout: 90 * 1000,
  expect: {
    timeout: 15000,
  },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report-integration' }]],
  use: {
    baseURL: process.env.MOASHIR_INTEGRATION_URL ?? 'http://localhost:5180',
    trace: 'retain-on-failure',
    headless: true,
  },
  projects: [
    {
      name: 'integration-ar-rtl',
      use: {
        ...devices['Desktop Chrome'],
        locale: 'ar-SA',
      },
    },
  ],
})
