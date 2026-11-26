import { expect } from '@playwright/test'

export const LANDING_SECTIONS = [
  'hero',
  'stats',
  'about',
  'model',
  'paths',
  'journey',
  'begin',
  'levels',
  'kit',
  'lenses',
  'leaders',
  'start',
  'contact',
]

export async function loadLanding(page, path = '/') {
  await page.goto(path)
  const title = page.locator('#lp-hero-title')
  await expect(async () => {
    const retry = page.locator('.lp-state button')
    if (await retry.isVisible()) await retry.click()
    await expect(title).toBeVisible({ timeout: 1000 })
  }).toPass({ timeout: 10000 })
  await expect(page.locator('.lp')).toHaveClass(/is-ready/)
}

export async function revealSection(page, id) {
  const section = page.locator(`#${id}`)
  await section.scrollIntoViewIfNeeded()
  await page.evaluate((anchor) => document.getElementById(anchor)?.scrollIntoView(), id)
  return section
}
