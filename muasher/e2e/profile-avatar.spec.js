import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { decisionAction } from './support/dialogs'

const PHOTO_SIDE = 240

async function photoBuffer(page) {
  const dataUrl = await page.evaluate((side) => {
    const canvas = document.createElement('canvas')
    canvas.width = side
    canvas.height = side
    const context = canvas.getContext('2d')
    context.fillStyle = '#1d7470'
    context.fillRect(0, 0, side, side)
    return canvas.toDataURL('image/png')
  }, PHOTO_SIDE)
  return Buffer.from(dataUrl.split(',')[1], 'base64')
}

test('a staff user replaces the person icon in the navbar with an uploaded photo', async ({
  page,
}) => {
  await seedSignedIn(
    page,
    { id: 7101, roles: ['assessor'], name: { ar: 'نورة بنت سعد', en: 'Noura Saad' } },
    { locale: 'en' },
  )
  await page.goto('/account')
  const avatar = page.locator('.navbar__avatar')
  await expect(avatar.locator('[data-icon="user"]')).toBeVisible()

  await page.getByTestId('profile-photo-input').setInputFiles({
    name: 'photo.png',
    mimeType: 'image/png',
    buffer: await photoBuffer(page),
  })
  await expect(avatar.locator('img')).toHaveAttribute('src', /\/api\/v1\/me\/avatar\?v=/)
  await expect(avatar.locator('img')).toHaveAttribute('alt', 'Profile photo of Noura Saad')

  await page.getByTestId('profile-photo-remove').click()
  await decisionAction(page).click()
  await expect(avatar.locator('img')).toHaveCount(0)
  await expect(avatar.locator('[data-icon="user"]')).toBeVisible()
})
