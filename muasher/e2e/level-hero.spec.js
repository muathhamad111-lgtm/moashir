import { test, expect } from '@playwright/test'
import { seedScenario, seedSignedIn } from './support/session'
import { clearMockFault, forceMockFault } from './support/mockFaults'

const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const HERO = '.level-hero'
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'

const PERSONAS = {
  none: { id: 7604, roles: ['entity'], entityId: 7004 },
  progress: { id: 7603, roles: ['entity'], entityId: 6003 },
  participation: { id: 7614, roles: ['entity'], entityId: 6004 },
  bronze: { id: 7615, roles: ['entity'], entityId: 7005 },
  silver: { id: 7601, roles: ['entity'], entityId: 6001 },
  gold: { id: 7602, roles: ['entity'], entityId: 6002 },
  diamond: { id: 7605, roles: ['entity'], entityId: 6005 },
}

const COPY = {
  ar: {
    none: 'لم تحصل جهتك على مستوى بعد',
    progress: 'التقييم المعتمد قيد الإجراء',
    participation: 'شهادة مشاركة',
    bronze: 'المستوى البرونزي',
    silver: 'المستوى الفضي',
    gold: 'المستوى الذهبي',
    diamond: 'المستوى الماسي',
    start: 'ابدأ التقييم المعتمد',
    follow: 'تابع طلبك',
    certificate: 'عرض الشهادة',
    participationAction: 'عرض شهادة المشاركة',
    expiring: /^تنتهي خلال 18 يومًا · \d{4}-\d{2}-\d{2}$/,
    expired: /^منتهية · \d{4}-\d{2}-\d{2}$/,
    valid: /^سارية · حتى \d{4}-\d{2}-\d{2}$/,
    score: 'الدرجة المعتمدة 74.5%',
    top: 'بلغت جهتك أعلى مستوى في النموذج',
  },
  en: {
    none: 'Your entity has no level yet',
    progress: 'Accredited assessment in progress',
    participation: 'Participation certificate',
    bronze: 'Bronze level',
    silver: 'Silver level',
    gold: 'Gold level',
    diamond: 'Diamond level',
    start: 'Start the accredited assessment',
    follow: 'Follow your request',
    certificate: 'View the certificate',
    participationAction: 'View the participation certificate',
    expiring: /^Expires in 18 days · \d{4}-\d{2}-\d{2}$/,
    expired: /^Expired · \d{4}-\d{2}-\d{2}$/,
    valid: /^Valid · until \d{4}-\d{2}-\d{2}$/,
    score: 'Accredited score 74.5%',
    top: 'Your entity has reached the highest level',
  },
}

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

async function openHome(page, persona, { locale, scenario = null, theme = 'light' }) {
  if (scenario) await seedScenario(page, scenario)
  await seedSignedIn(page, persona, { locale, theme })
  await page.goto('/dashboard')
  await page.addStyleTag({ content: DEV_OVERLAYS })
  const hero = page.locator(HERO)
  await expect(hero).toHaveAttribute('data-phase', 'static', { timeout: 15000 })
  await expect(hero.locator('.level-hero__name')).toBeVisible()
  return hero
}

test.describe('entity home level card — states', () => {
  test.beforeEach(async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize(DESKTOP)
  })

  const UNSCORED = [
    ['none', 'none', 'start'],
    ['progress', 'progress', 'follow'],
  ]
  const SCORED = [
    ['participation', 'participation', 'participationAction'],
    ['bronze', 'bronze', 'certificate'],
    ['silver', 'silver', 'certificate'],
    ['gold', 'gold', 'certificate'],
    ['diamond', 'diamond', 'certificate'],
  ]

  async function expectState(page, persona, tier, action, testInfo) {
    const copy = COPY[localeOf(testInfo)]
    const hero = await openHome(page, PERSONAS[persona], { locale: localeOf(testInfo) })
    await expect(hero).toHaveAttribute('data-tier', tier)
    await expect(hero.locator('.level-hero__name')).toHaveText(copy[tier])
    await expect(hero.locator('[data-testid="level-hero-action"]')).toHaveText(copy[action])
    await expect(hero.locator('.level-ring')).toHaveCount(0)
    await expect(hero.locator('.level-track__svg')).toBeVisible()
    return hero
  }

  for (const [persona, tier, action] of UNSCORED) {
    test(`renders the ${persona} state without a score`, async ({ page }, testInfo) => {
      const hero = await expectState(page, persona, tier, action, testInfo)
      await expect(hero.locator('[data-testid="level-validity"]')).toHaveCount(0)
      await expect(hero.locator('.level-hero__number')).toHaveCount(0)
    })
  }

  for (const [persona, tier, action] of SCORED) {
    test(`renders the ${persona} state with its score and validity`, async ({ page }, testInfo) => {
      const hero = await expectState(page, persona, tier, action, testInfo)
      await expect(hero.locator('[data-testid="level-validity"]')).toHaveText(
        COPY[localeOf(testInfo)].valid,
      )
      await expect(hero.locator('.level-hero__number')).toHaveCount(1)
    })
  }

  test('shows the silver score once with its final accessible value', async ({
    page,
  }, testInfo) => {
    const copy = COPY[localeOf(testInfo)]
    const hero = await openHome(page, PERSONAS.silver, { locale: localeOf(testInfo) })
    await expect(hero.locator('.level-hero__number')).toHaveText('74.5')
    await expect(hero.locator('.level-hero__figure .level-hero__sr')).toHaveText(copy.score)
    await expect(hero.locator('[data-testid="level-reassessment"]')).toBeVisible()
    await expect(hero.locator('[data-testid="level-meta"]')).toContainText('CRT-2026-000003')
    await expect(hero.locator('[data-testid="hero-next-level"]')).toBeVisible()
  })

  test('tells the top level without a remaining zone', async ({ page }, testInfo) => {
    const copy = COPY[localeOf(testInfo)]
    const hero = await openHome(page, PERSONAS.diamond, { locale: localeOf(testInfo) })
    await expect(hero.locator('.level-hero__remaining')).toHaveText(copy.top)
    await expect(hero.locator('.level-track__remaining')).toHaveCount(0)
    await expect(hero.locator('[data-testid="hero-next-level"]')).toHaveCount(0)
  })

  test('keeps the skeleton busy while the level loads', async ({ page }, testInfo) => {
    await seedScenario(page, 'certificate-pending')
    await seedSignedIn(page, PERSONAS.silver, { locale: localeOf(testInfo), theme: 'light' })
    await page.goto('/dashboard')
    const hero = page.locator(HERO)
    await expect(hero.locator('[data-testid="level-hero-skeleton"]')).toBeVisible({
      timeout: 15000,
    })
    await expect(hero).toHaveAttribute('aria-busy', 'true')
    await expect(hero.locator('.level-hero__name')).toHaveCount(0)
  })

  test('reports a load failure and recovers on retry', async ({ page }, testInfo) => {
    await forceMockFault(page, { method: 'GET', path: '/api/v1/certificates', status: 503 })
    await seedSignedIn(page, PERSONAS.silver, { locale: localeOf(testInfo), theme: 'light' })
    await page.goto('/dashboard')
    const hero = page.locator(HERO)
    const alert = hero.locator('.level-hero__error[role="alert"]')
    await expect(alert).toBeVisible({ timeout: 15000 })
    await clearMockFault(page)
    await alert.locator('button').click()
    await expect(hero.locator('.level-hero__name')).toHaveText(COPY[localeOf(testInfo)].silver)
  })
})

test.describe('entity home level card — certificate validity actions', () => {
  test.beforeEach(async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize(DESKTOP)
  })

  test('asks for the accredited assessment when the certificate expires soon', async ({
    page,
  }, testInfo) => {
    const copy = COPY[localeOf(testInfo)]
    const hero = await openHome(page, PERSONAS.diamond, {
      locale: localeOf(testInfo),
      scenario: 'certificate-expiring',
    })
    await expect(hero).toHaveAttribute('data-validity', 'expiring_soon')
    await expect(hero.locator('[data-testid="level-validity"]')).toHaveText(copy.expiring)
    await expect(hero.locator('.level-hero__name')).toHaveText(copy.diamond)
    const action = hero.locator('[data-testid="level-hero-action"]')
    await expect(action).toHaveText(copy.start)
    await action.click()
    await expect(page).toHaveURL(/accredited/)
  })

  test('keeps the expired level muted, worded and with the start action', async ({
    page,
  }, testInfo) => {
    const copy = COPY[localeOf(testInfo)]
    const hero = await openHome(page, PERSONAS.bronze, {
      locale: localeOf(testInfo),
      scenario: 'certificate-expired',
    })
    await expect(hero).toHaveAttribute('data-validity', 'expired')
    await expect(hero).toHaveClass(/level-hero--expired/)
    await expect(hero.locator('.level-hero__name')).toHaveText(copy.bronze)
    await expect(hero.locator('[data-testid="level-validity"]')).toHaveText(copy.expired)
    await expect(hero.locator('[data-testid="hero-next-level"]')).toHaveCount(0)
    const seal = hero.locator('.level-hero__medal .level-emblem')
    await expect(seal).toHaveCSS('opacity', '0.45')
    const action = hero.locator('[data-testid="level-hero-action"]')
    await expect(action).toHaveText(copy.start)
    await action.click()
    await expect(page).toHaveURL(/accredited/)
  })

  test('follows the open request instead when a re-assessment is already running', async ({
    page,
  }, testInfo) => {
    const copy = COPY[localeOf(testInfo)]
    const hero = await openHome(page, PERSONAS.silver, {
      locale: localeOf(testInfo),
      scenario: 'certificate-expired',
    })
    await expect(hero.locator('[data-testid="level-validity"]')).toHaveText(copy.expired)
    await expect(hero.locator('[data-testid="level-hero-action"]')).toHaveText(copy.follow)
  })
})

test.describe('entity home level card — motion', () => {
  test('shows the final state at once under reduced motion', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize(DESKTOP)
    const hero = await openHome(page, PERSONAS.silver, { locale: localeOf(testInfo) })
    await expect(hero).not.toHaveClass(/level-hero--(pending|playing)/)
    await expect(hero.locator('.level-hero__number')).toHaveText('74.5')
    const running = await page.evaluate(() =>
      document
        .getAnimations()
        .filter((animation) => animation.effect?.target?.closest?.('.level-hero'))
        .map((animation) => ({
          name: animation.animationName,
          duration: animation.effect.getTiming().duration,
        })),
    )
    expect(running.every((item) => item.name === 'level-hero-fade' && item.duration <= 150)).toBe(
      true,
    )
  })

  test('plays the entrance once per session and keeps the final value accessible', async ({
    page,
  }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'no-preference' })
    await page.setViewportSize(DESKTOP)
    await seedSignedIn(page, PERSONAS.silver, { locale: localeOf(testInfo), theme: 'light' })
    await page.goto('/dashboard')
    const hero = page.locator(HERO)
    await expect(hero).toHaveAttribute('data-phase', 'playing', { timeout: 15000 })
    await expect(hero.locator('.level-hero__figure .level-hero__sr')).toHaveText(
      COPY[localeOf(testInfo)].score,
    )
    await expect(hero.locator('.level-hero__figure-value')).toHaveAttribute('aria-hidden', 'true')
    await expect(hero).toHaveAttribute('data-phase', 'static', { timeout: 5000 })
    await expect(hero.locator('.level-hero__number')).toHaveText('74.5')
    await page.goto('/payments')
    await page.goto('/dashboard')
    await expect(page.locator(HERO)).toHaveAttribute('data-phase', 'static', { timeout: 15000 })
    await expect(page.locator(HERO)).not.toHaveClass(/level-hero--playing/)
  })
})

test.describe('entity home level card — 390 px', () => {
  test('keeps the score and the distance to the next level in the first screen', async ({
    page,
  }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize(MOBILE)
    const hero = await openHome(page, PERSONAS.silver, { locale: localeOf(testInfo) })
    const tabBar = await page.locator('.mobile-tab-bar').boundingBox()
    const remaining = await hero.locator('.level-hero__remaining').boundingBox()
    const score = await hero.locator('.level-hero__number').boundingBox()
    expect(score.y + score.height).toBeLessThanOrEqual(tabBar.y)
    expect(remaining.y + remaining.height).toBeLessThanOrEqual(tabBar.y)
    await expect(hero.locator('[data-testid="level-meta"]')).toBeHidden()
    const card = await hero.boundingBox()
    const action = await hero.locator('[data-testid="level-hero-action"]').boundingBox()
    expect(action.width).toBeGreaterThan(card.width * 0.8)
    expect(action.height).toBeGreaterThanOrEqual(24)
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
    )
    expect(overflow).toBeLessThanOrEqual(1)
  })
})
