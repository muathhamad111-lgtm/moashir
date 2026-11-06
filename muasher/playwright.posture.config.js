import process from 'node:process'
import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './e2e/posture',
  timeout: 60 * 1000,
  expect: { timeout: 15000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: process.env.MOASHIR_INTEGRATION_URL ?? 'http://localhost:5180',
    headless: true,
  },
  projects: [{ name: 'posture-ar-rtl', use: { ...devices['Desktop Chrome'], locale: 'ar-SA' } }],
})
