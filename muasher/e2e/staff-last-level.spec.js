import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedScenario, seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'
import { chooseValue } from './support/dropdown'

const SHOTS = '../agents/tasks/reports/Task-84'
const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const ADMIN = { id: 9101, roles: ['system_admin'], name: 'مدير النظام' }
const FOLLOWER = { id: 8, roles: ['request_follower'], name: 'متابع الطلبات' }
const ALBIRR = { id: 5001, reference: 'ENT-005001', tier: 'silver' }
const EHSAN = { id: 6002, reference: 'ENT-006002', tier: 'gold', request: 9108 }
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'
const STATES = [
  { key: 'valid', scenario: null, chip: null },
  { key: 'expiring', scenario: 'certificate-expiring', chip: 'expiring_soon' },
  { key: 'expired', scenario: 'certificate-expired', chip: 'expired' },
]
const THEMES = ['light', 'dark']
const CHIP_TEXT = {
  ar: {
    expiring_soon: /^تنتهي خلال 18 يومًا · \d{4}-\d{2}-\d{2}$/,
    expired: /^منتهية · \d{4}-\d{2}-\d{2}$/,
  },
  en: {
    expiring_soon: /^Expires in 18 days · \d{4}-\d{2}-\d{2}$/,
    expired: /^Expired · \d{4}-\d{2}-\d{2}$/,
  },
}
const EXPIRED_OPTION = { ar: 'مستوى منتهٍ', en: 'Expired level' }
const NO_LEVEL_OPTION = { ar: 'بلا مستوى', en: 'No level' }
const LEVEL_FILTER = '[data-testid="entity-level-filter"] [role="combobox"]'

mkdirSync(SHOTS, { recursive: true })

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function suffixOf(testInfo) {
  return localeOf(testInfo) === 'en' ? 'en-ltr' : 'ar-rtl'
}

async function signIn(page, user, testInfo, { scenario = null, theme = 'light' } = {}) {
  if (scenario) await seedScenario(page, scenario)
  await seedSignedIn(page, user, { locale: localeOf(testInfo), theme })
}

async function open(page, path, ready) {
  await page.goto(path)
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await expect(page.locator(ready).first()).toBeVisible({ timeout: 15000 })
}

async function capture(target, page, name, testInfo) {
  await settleFonts(page)
  await target.screenshot({
    path: `${SHOTS}/${name}-${suffixOf(testInfo)}.png`,
    ...(target === page ? { fullPage: true } : {}),
  })
}

const rows = (page) => page.locator('.table:not(.table--loading) tbody .table__row')
const rowOf = (page, reference) => rows(page).filter({ hasText: reference })

async function expectChip(scope, chip, testInfo) {
  const chips = scope.locator('[data-testid="level-validity"]')
  const expected = chip ? [chip] : []
  expect(await chips.evaluateAll((items) => items.map((item) => item.dataset.validity))).toEqual(
    expected,
  )
  await expect(chips).toHaveText(expected.map((key) => CHIP_TEXT[localeOf(testInfo)][key]))
}

async function expectLevel(scope, { tier, chip }, testInfo) {
  const badge = scope.locator('.level-badge').first()
  await expect(badge).toHaveAttribute('data-tier', tier)
  await expectChip(scope, chip, testInfo)
  expect(await badge.evaluate((item) => item.classList.contains('level-badge--expired'))).toBe(
    chip === 'expired',
  )
}

function contrastProbe(selector) {
  const parse = (value) => {
    const srgb = value.match(/color\(srgb ([\d.]+) ([\d.]+) ([\d.]+)(?: \/ ([\d.]+))?\)/)
    if (srgb)
      return [
        srgb[1] * 255,
        srgb[2] * 255,
        srgb[3] * 255,
        srgb[4] === undefined ? 1 : Number(srgb[4]),
      ]
    const rgb = value.match(/rgba?\(([\d.]+),? ([\d.]+),? ([\d.]+)(?:,? \/?\s?([\d.]+))?\)/)
    return rgb
      ? [Number(rgb[1]), Number(rgb[2]), Number(rgb[3]), rgb[4] === undefined ? 1 : Number(rgb[4])]
      : null
  }
  const blend = (top, bottom) =>
    top
      .slice(0, 3)
      .map((c, i) => c * top[3] + bottom[i] * (1 - top[3]))
      .concat(1)
  const luminance = ([r, g, b]) => {
    const channel = (c) => {
      const s = c / 255
      return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
    }
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)
  }
  const ratio = (a, b) => {
    const [high, low] = [luminance(a), luminance(b)].sort((x, y) => y - x)
    return (high + 0.05) / (low + 0.05)
  }
  const backgroundsOf = (element) => {
    const layers = []
    for (let node = element; node; node = node.parentElement) {
      const style = getComputedStyle(node)
      const stops = (style.backgroundImage.match(/(rgba?|color)\([^)]*\)/g) ?? [])
        .map(parse)
        .filter(Boolean)
      if (stops.length) return { layers, stops }
      const color = parse(style.backgroundColor)
      if (color && color[3] > 0) {
        layers.push(color)
        if (color[3] === 1) return { layers, stops: [] }
      }
    }
    return { layers, stops: [[255, 255, 255, 1]] }
  }
  const element = document.querySelector(selector)
  const { layers, stops } = backgroundsOf(element)
  const bases = stops.length ? stops.map((stop) => blend(stop, [255, 255, 255, 1])) : [layers.pop()]
  const surfaces = bases.map((base) =>
    layers.reduceRight((under, layer) => blend(layer, under), base),
  )
  const ink = parse(getComputedStyle(element).color)
  const values = surfaces.map((surface) => ratio(blend(ink, surface), surface))
  return {
    color: getComputedStyle(element).color,
    fontSize: getComputedStyle(element).fontSize,
    fontWeight: getComputedStyle(element).fontWeight,
    min: Math.round(Math.min(...values) * 100) / 100,
  }
}

async function measure(page, selector, label, testInfo, theme) {
  const result = await page.evaluate(contrastProbe, selector)
  console.log(`CONTRAST ${label} ${suffixOf(testInfo)} ${theme}: ${JSON.stringify(result)}`)
  return result
}

test.beforeEach(async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.setViewportSize(DESKTOP)
})

test.describe('Task-84 — S1 entity table', () => {
  for (const state of STATES) {
    for (const theme of THEMES) {
      test(`shows the ${state.key} last level on the entity row (${theme})`, async ({
        page,
      }, testInfo) => {
        await signIn(page, ADMIN, testInfo, { scenario: state.scenario, theme })
        await open(page, '/admin/entities', '.table__row .level-badge')
        const row = rowOf(page, ALBIRR.reference)
        await expect(row).toHaveCount(1)
        await expectLevel(row, { tier: ALBIRR.tier, chip: state.chip }, testInfo)
        await capture(page, page, `s1-entity-table-${state.key}-${theme}`, testInfo)
      })
    }
  }

  test('lists «مستوى منتهٍ» after «بلا مستوى», round-trips it in the URL and keeps counting valid levels', async ({
    page,
  }, testInfo) => {
    const locale = localeOf(testInfo)
    await signIn(page, ADMIN, testInfo, { scenario: 'certificate-expired' })
    await open(page, '/admin/entities', '.table__row .level-badge')
    await page.locator(LEVEL_FILTER).click()
    const labels = (await page.getByRole('option').allTextContents()).map((text) => text.trim())
    const noLevel = labels.indexOf(NO_LEVEL_OPTION[locale])
    expect(noLevel).toBeGreaterThan(-1)
    expect(labels[noLevel + 1]).toBe(EXPIRED_OPTION[locale])
    await capture(page, page, 's5-filter-open-expired-option', testInfo)
    await page.keyboard.press('Escape')

    await chooseValue(page.locator(LEVEL_FILTER), 'expired')
    await expect(page).toHaveURL(/[?&]level=expired/)
    await expectLevel(
      rowOf(page, ALBIRR.reference),
      { tier: ALBIRR.tier, chip: 'expired' },
      testInfo,
    )
    await page.reload()
    await page.addStyleTag({ content: DEV_OVERLAYS })
    await expect(page).toHaveURL(/[?&]level=expired/)
    await expect(rowOf(page, ALBIRR.reference)).toHaveCount(1)

    await chooseValue(page.locator(LEVEL_FILTER), 'silver')
    await expect(page).toHaveURL(/[?&]level=silver/)
    await expect(rowOf(page, ALBIRR.reference)).toHaveCount(0)
    await chooseValue(page.locator(LEVEL_FILTER), 'none')
    await expect(page).toHaveURL(/[?&]level=none/)
    await expect(rowOf(page, ALBIRR.reference)).toHaveCount(1)
  })

  test('wraps the chip under the badge at 390 px without a horizontal page scroll', async ({
    page,
  }, testInfo) => {
    await page.setViewportSize(MOBILE)
    await signIn(page, ADMIN, testInfo, { scenario: 'certificate-expired' })
    await open(page, '/admin/entities?level=expired', '.table__row .level-badge')
    const row = rowOf(page, ALBIRR.reference)
    await expectLevel(row, { tier: ALBIRR.tier, chip: 'expired' }, testInfo)
    const badge = await row.locator('.level-badge').boundingBox()
    const chip = await row.locator('[data-testid="level-validity"]').boundingBox()
    const cell = await row.locator('.level-badge__group').boundingBox()
    expect(chip.y).toBeGreaterThanOrEqual(badge.y)
    expect(chip.x).toBeGreaterThanOrEqual(cell.x - 1)
    expect(chip.x + chip.width).toBeLessThanOrEqual(cell.x + cell.width + 1)
    const dateLines = await row
      .locator('[data-testid="level-validity"] bdi')
      .evaluate((item) => item.getClientRects().length)
    expect(dateLines).toBe(1)
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - window.innerWidth,
    )
    expect(overflow).toBeLessThanOrEqual(0)
    await capture(page, page, 's1-entity-table-expired-390', testInfo)
    await row.locator('.level-badge__group').scrollIntoViewIfNeeded()
    await settleFonts(page)
    await page.screenshot({
      path: `${SHOTS}/s1-entity-table-expired-390-cell-${suffixOf(testInfo)}.png`,
    })
  })
})

test.describe('Task-84 — S2 record level card and record header', () => {
  for (const state of STATES) {
    for (const theme of THEMES) {
      test(`shows the ${state.key} level on the record ribbon (${theme})`, async ({
        page,
      }, testInfo) => {
        await signIn(page, ADMIN, testInfo, { scenario: state.scenario, theme })
        await open(page, `/admin/entities/${ALBIRR.id}`, '[data-testid="level-ribbon"]')
        const ribbon = page.locator('[data-testid="level-ribbon"]')
        await expect(ribbon).toHaveAttribute('data-tier', ALBIRR.tier)
        await expect(page.locator('.entity-record__heading .level-badge')).toHaveCount(0)
        await expectChip(ribbon, state.chip, testInfo)
        expect(
          await ribbon.evaluate((item) => item.classList.contains('level-ribbon--expired')),
        ).toBe(state.chip === 'expired')
        await expect(page.locator('.level-history__chip--current')).toBeVisible()
        await capture(
          page.locator('.entity-record__level'),
          page,
          `s2-record-ribbon-${state.key}-${theme}`,
          testInfo,
        )
      })

      test(`shows the ${state.key} level in the record header without the certificates permission (${theme})`, async ({
        page,
      }, testInfo) => {
        await signIn(page, FOLLOWER, testInfo, { scenario: state.scenario, theme })
        await open(page, `/admin/entities/${ALBIRR.id}`, '.entity-record__heading .level-badge')
        await expect(page.locator('[data-testid="level-ribbon"]')).toHaveCount(0)
        await expectLevel(
          page.locator('.entity-record__heading'),
          { tier: ALBIRR.tier, chip: state.chip },
          testInfo,
        )
        await capture(
          page.locator('.entity-record__header'),
          page,
          `s2-record-header-${state.key}-${theme}`,
          testInfo,
        )
      })
    }
  }
})

test.describe('Task-84 — S3 request queue and S4 request detail', () => {
  for (const state of STATES) {
    for (const theme of THEMES) {
      test(`shows the ${state.key} level of the entity behind a request (${theme})`, async ({
        page,
      }, testInfo) => {
        await signIn(page, FOLLOWER, testInfo, { scenario: state.scenario, theme })
        await open(page, '/admin/requests', '.request-queue__entity .level-badge')
        const cell = page
          .locator('.request-queue__entity')
          .filter({ has: page.locator(`.level-badge[data-tier="${EHSAN.tier}"]`) })
          .first()
        await expect(cell).toBeVisible()
        await expectLevel(cell, { tier: EHSAN.tier, chip: state.chip }, testInfo)
        await capture(page, page, `s3-request-queue-${state.key}-${theme}`, testInfo)

        await open(
          page,
          `/admin/requests/${EHSAN.request}`,
          '[data-testid="request-entity"] .level-badge',
        )
        const header = page.locator('.request-detail__header')
        await expect(header).toBeVisible()
        await expectLevel(
          page.locator('[data-testid="request-entity"]'),
          { tier: EHSAN.tier, chip: state.chip },
          testInfo,
        )
        await capture(header, page, `s4-request-detail-${state.key}-${theme}`, testInfo)
      })
    }
  }
})

test.describe('Task-84 — readable muted level names (WCAG 2.1 AA)', () => {
  for (const theme of THEMES) {
    test(`keeps the expired badge names, the chip and the ribbon name readable (${theme})`, async ({
      page,
    }, testInfo) => {
      await signIn(page, ADMIN, testInfo, { scenario: 'certificate-expired', theme })
      await open(page, '/admin/entities?level=expired', '.level-badge--expired')
      const tableName = await measure(
        page,
        '.level-badge--expired .level-badge__label',
        'S1 badge name',
        testInfo,
        theme,
      )
      const chip = await measure(
        page,
        '.level-hero__chip--expired',
        'S1 expired chip',
        testInfo,
        theme,
      )
      await open(page, `/admin/entities/${ALBIRR.id}`, '.level-ribbon--expired')
      const ribbonName = await measure(
        page,
        '.level-ribbon--expired .level-ribbon__name',
        'S2 ribbon name',
        testInfo,
        theme,
      )
      expect(tableName.min).toBeGreaterThanOrEqual(4.5)
      expect(chip.min).toBeGreaterThanOrEqual(4.5)
      expect(ribbonName.min).toBeGreaterThanOrEqual(4.5)
    })

    test(`keeps the expiring chip and the expired header badge readable (${theme})`, async ({
      page,
    }, testInfo) => {
      await signIn(page, FOLLOWER, testInfo, { scenario: 'certificate-expired', theme })
      await open(
        page,
        `/admin/entities/${ALBIRR.id}`,
        '.entity-record__heading .level-badge--expired',
      )
      const headerName = await measure(
        page,
        '.entity-record__heading .level-badge__label',
        'S2 header badge name',
        testInfo,
        theme,
      )
      await page.context().clearCookies({ name: 'moashir_mock_scenario' })
      await signIn(page, FOLLOWER, testInfo, { scenario: 'certificate-expiring', theme })
      await open(page, '/admin/requests', '.level-hero__chip--expiring')
      const expiring = await measure(
        page,
        '.level-hero__chip--expiring',
        'S3 expiring chip',
        testInfo,
        theme,
      )
      expect(headerName.min).toBeGreaterThanOrEqual(4.5)
      expect(expiring.min).toBeGreaterThanOrEqual(4.5)
    })
  }
})
