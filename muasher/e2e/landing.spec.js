import { mkdirSync, readFileSync } from 'node:fs'
import process from 'node:process'
import { test, expect } from '@playwright/test'
import { seedPreferences, seedSignedIn } from './support/session'
import { chooseNamed } from './support/dropdown'
import { closeSuccess, decisionAction } from './support/dialogs'
import { AA_TEXT, contrastOf } from './support/contrast'
import { licensedFontsPresent } from '../vite-plugins/licensedFonts.js'
import { LANDING_SECTIONS, loadLanding, revealSection } from './support/landing'

const SHOTS = 'e2e/__screenshots__/landing'
const CONTENT = JSON.parse(
  readFileSync(new URL('../../backend/database/data/content/landing-home.json', import.meta.url)),
)
const SECTION_BY_KEY = Object.fromEntries(
  CONTENT.page.sections.map((section) => [section.key, section]),
)
const VIEWPORTS = {
  mobile: { width: 375, height: 812 },
  tablet: { width: 768, height: 1024 },
  desktop: { width: 1440, height: 900 },
}
const THEMES = ['light', 'dark']
const OPPOSITE_THEME = { dark: 'light', light: 'dark' }
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'
const MESSAGE = {
  ar: 'نرغب في معرفة متطلبات التقييم المعتمد والمدة المتوقعة للمراجعة.',
  en: 'We would like to know the accredited assessment requirements and the expected review time.',
}

mkdirSync(SHOTS, { recursive: true })

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function sectionRow(page, key) {
  return page
    .locator('.sortable-list__row')
    .filter({ has: page.locator('.cms-section-row__key', { hasText: new RegExp(`^${key}$`) }) })
}

function suffixOf(testInfo) {
  return localeOf(testInfo) === 'en' ? 'en-ltr' : 'ar-rtl'
}

async function open(page, testInfo, { theme = 'light', reduced = false } = {}) {
  if (reduced) await page.emulateMedia({ reducedMotion: 'reduce' })
  await seedPreferences(page, { locale: localeOf(testInfo), theme })
  await loadLanding(page)
  await page.addStyleTag({ content: DEV_OVERLAYS })
}

async function horizontalOverflow(page) {
  return page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
}

test.describe('landing — content from the CMS', () => {
  test('renders every published section in the CMS order with its own copy', async ({
    page,
  }, testInfo) => {
    const locale = localeOf(testInfo)
    await open(page, testInfo, { reduced: true })
    const ids = await page
      .locator('#lp-main .lp-page > [id]')
      .evaluateAll((nodes) => nodes.map((node) => node.id))
    expect(ids).toEqual(LANDING_SECTIONS)

    await expect(page.locator('#lp-hero-title')).toContainText(SECTION_BY_KEY.hero.title[locale])
    for (const key of ['about', 'model', 'paths', 'journey', 'levels', 'kit', 'lenses']) {
      const section = page.locator(`#${key === 'model' ? 'model' : key}`)
      await expect(section.locator('h2').first()).toContainText(SECTION_BY_KEY[key].title[locale])
    }
    await expect(page.locator('#begin h2')).toHaveText(SECTION_BY_KEY.mid_cta.title[locale])
    await expect(page.locator('#start h2')).toHaveText(SECTION_BY_KEY.start.title[locale])
    await expect(page.locator('#leaders h2')).toContainText(SECTION_BY_KEY.leaders.title[locale])
    await expect(page.locator('#contact h2')).toHaveText(SECTION_BY_KEY.contact.title[locale])
    await expect(page.locator('#partners')).toHaveCount(0)
    await expect(page.locator('.lp-assist-btn')).toBeVisible()
    await expect(page.locator('html')).toHaveAttribute('dir', locale === 'en' ? 'ltr' : 'rtl')
  })

  test('shows the platform prices before VAT, the free path and the configured level thresholds', async ({
    page,
  }, testInfo) => {
    await open(page, testInfo, { reduced: true })
    const paths = page.locator('#paths .lp-path')
    await expect(paths).toHaveCount(3)
    await expect(paths.nth(0).locator('.lp-path__badge')).toBeVisible()
    await expect(paths.nth(1).locator('.lp-path__cost')).toContainText(/\d/)
    await expect(page.locator('#levels .lp-lv__tiers button')).toHaveCount(5)
    await expect(page.locator('.lp-seal')).toHaveCount(4)
  })

  test('a Content Officer edits landing fields in the dashboard and the published page shows them', async ({
    page,
  }, testInfo) => {
    const locale = localeOf(testInfo)
    const hint = { ar: 'اسحب المؤشر لترى مستوى جهتك.', en: 'Drag the marker to see your level.' }
    const label = { ar: 'تخدم منصة مؤشر', en: 'Moashir serves' }
    await seedSignedIn(page, { roles: ['content_officer'], name: 'Content Officer' }, { locale })
    await page.goto('/admin/settings/homepage')
    await expect(page.locator('.cms-section-row').first()).toBeVisible({ timeout: 15000 })

    for (const [key, field, value, tag] of [
      ['levels', 'hint', hint, 'textarea'],
      ['about', 'audienceLabel', label, 'input'],
    ]) {
      const row = sectionRow(page, key)
      await row.locator('.cms-section-row__actions .btn--secondary').click()
      const dialog = page.locator('.modal').last()
      await dialog.locator(`${tag}[name="metaFields.${field}.ar"]`).fill(value.ar)
      await dialog.locator(`${tag}[name="metaFields.${field}.en"]`).fill(value.en)
      await dialog.locator('.modal__footer .btn--primary').click()
      await expect(dialog).toBeHidden()
    }
    await page.locator('.publication-control .btn--primary').click()
    await decisionAction(page).click()
    await closeSuccess(page)

    await page.locator('.publication-control__view').click()
    await expect(page).toHaveURL(/\/$/)
    await expect(page.locator('#lp-lv-hint')).toHaveText(hint[locale], { timeout: 15000 })
    await expect(page.locator('.lp-serves__label')).toHaveText(label[locale])
  })

  test('the editor refuses a half-translated landing field', async ({ page }, testInfo) => {
    const locale = localeOf(testInfo)
    await seedSignedIn(page, { roles: ['content_officer'], name: 'Content Officer' }, { locale })
    await page.goto('/admin/settings/homepage')
    await expect(page.locator('.cms-section-row').first()).toBeVisible({ timeout: 15000 })
    const row = sectionRow(page, 'hero')
    await row.locator('.cms-section-row__actions .btn--secondary').click()
    const dialog = page.locator('.modal').last()
    await dialog.locator('input[name="metaFields.scoreCaption.en"]').fill('')
    await dialog.locator('.modal__footer .btn--primary').click()
    await expect(dialog).toBeVisible()
    await expect(dialog.locator('.field__error').first()).toBeVisible()
  })
})

test.describe('landing — interactions', () => {
  test('the theme switch changes and remembers the theme', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page
      .context()
      .addCookies([{ name: 'locale', value: localeOf(testInfo), url: 'http://localhost' }])
    await loadLanding(page)
    const html = page.locator('html')
    const before = await html.getAttribute('data-theme')
    const toggle = page.locator('.lp-theme-switch')
    await toggle.click()
    const after = OPPOSITE_THEME[before]
    await expect(html).toHaveAttribute('data-theme', after)
    await expect(toggle).toHaveAttribute('aria-checked', String(after === 'dark'))
    await page.reload()
    await loadLanding(page)
    await expect(html).toHaveAttribute('data-theme', after)
  })

  test('the levels respond to the tier buttons and to the keyboard', async ({ page }, testInfo) => {
    await open(page, testInfo, { reduced: true })
    await revealSection(page, 'levels')
    const name = page.locator('.lp-lv__name')
    await page.locator('.lp-lv__tiers button').nth(1).click()
    await expect(page.locator('.lp-lv__tiers button').nth(1)).toHaveAttribute(
      'aria-pressed',
      'true',
    )
    const range = page.locator('#lp-lv-range')
    await range.focus()
    await page.keyboard.press('End')
    await expect(page.locator('.lp-lv__num span')).toHaveText('100')
    await expect(range).toHaveAttribute('aria-valuetext', /100/)
    await page.keyboard.press('Home')
    await expect(page.locator('.lp-lv__num span')).toHaveText('0')
    await expect(name).not.toHaveText('')
  })

  test('the levels follow a drag along the scale in both directions', async ({
    page,
  }, testInfo) => {
    await open(page, testInfo, { reduced: true })
    await revealSection(page, 'levels')
    const track = page.locator('.lp-lv__track')
    await track.scrollIntoViewIfNeeded()
    const box = await track.boundingBox()
    const startSide = { en: box.x + 2, ar: box.x + box.width - 2 }[localeOf(testInfo)]
    await page.mouse.click(startSide, box.y + box.height / 2)
    await expect(page.locator('.lp-lv__num span')).toHaveText(/^[0-2]$/)
  })

  test('the Kit steps drive the gap map and the lenses answer clicks', async ({
    page,
  }, testInfo) => {
    await open(page, testInfo, { reduced: true })
    await revealSection(page, 'kit')
    const stage = page.locator('.lp-kit__stage')
    await expect(stage).toHaveClass(/is-s3/)
    await page.locator('.lp-kit__steps button').nth(1).click()
    await expect(stage).toHaveClass(/is-s1/)
    await expect(stage).not.toHaveClass(/is-s2/)

    await revealSection(page, 'lenses')
    const lenses = page.locator('.lp-lenses__list button')
    await lenses.nth(3).click()
    await expect(lenses.nth(3)).toHaveAttribute('aria-pressed', 'true')
    await expect(page.locator('.lp-lenses__center-name')).toHaveText(
      (await lenses.nth(3).innerText()).split('\n')[0],
    )
  })

  test('the journey line fills and lights the steps while scrolling', async ({
    page,
  }, testInfo) => {
    await open(page, testInfo)
    const journey = await revealSection(page, 'journey')
    await expect(page.locator('.lp-jt__dot')).toHaveCount(6)
    await journey.evaluate((node) => {
      const bottom = node.getBoundingClientRect().bottom + window.scrollY
      window.scrollTo({ top: bottom, behavior: 'instant' })
    })
    await expect(page.locator('.lp-jr.is-on')).toHaveCount(6)
    const offset = Number(await page.locator('.lp-jt__line').getAttribute('stroke-dashoffset'))
    expect(offset).toBeLessThan(5)
  })

  test('a guest sends the contact form and receives a reference', async ({ page }, testInfo) => {
    const locale = localeOf(testInfo)
    await open(page, testInfo, { reduced: true })
    const form = page.locator('#contact form')
    await form.scrollIntoViewIfNeeded()
    await form.locator('button[type="submit"]').click()
    await expect(form.locator('.field__error').first()).toBeVisible()
    await form
      .locator('input[name="name"]')
      .fill(locale === 'en' ? 'Sara Al-Abdullah' : 'سارة العبدالله')
    await form.locator('input[name="email"]').fill('sara.alabdullah@example.sa')
    await chooseNamed(form, 'enquiryType', 'payments')
    await form.locator('textarea[name="message"]').fill(MESSAGE[locale])
    await form.locator('input[type="checkbox"]').check()
    await form.locator('button[type="submit"]').click()
    await expect(page.locator('.contact-form__reference-value')).toHaveText(/^CNT-\d{4}-\d{6}$/, {
      timeout: 10000,
    })
  })

  test('the assistant answers common questions without any outside service', async ({
    page,
  }, testInfo) => {
    const outside = []
    page.on('request', (request) => {
      if (!request.url().startsWith('http://localhost')) outside.push(request.url())
    })
    await open(page, testInfo, { reduced: true })
    const button = page.locator('.lp-assist-btn')
    await button.click()
    const dialog = page.locator('#lp-assist')
    await expect(dialog).toBeVisible()
    await expect(dialog.locator('input')).toBeFocused()
    await dialog.locator('.lp-assist__sugs button').first().click()
    await expect(dialog.locator('.lp-assist__reply p')).not.toHaveText('')
    await dialog
      .locator('input')
      .fill(localeOf(testInfo) === 'en' ? 'How long is the review?' : 'كم مدة المراجعة؟')
    await dialog.locator('input').press('Enter')
    await expect(dialog.locator('.lp-assist__cta')).toHaveAttribute('href', '#contact')
    await page.keyboard.press('Escape')
    await expect(dialog).toBeHidden()
    await expect(button).toBeFocused()
    expect(outside).toEqual([])
  })

  test('the mobile menu opens, closes with Escape and scrolls to a section', async ({
    page,
  }, testInfo) => {
    await page.setViewportSize(VIEWPORTS.mobile)
    await open(page, testInfo, { reduced: true })
    const burger = page.locator('.lp-top__burger')
    await expect(burger).toBeVisible()
    await expect(page.locator('#lp-nav')).toBeHidden()
    await burger.click()
    await expect(burger).toHaveAttribute('aria-expanded', 'true')
    await expect(page.locator('#lp-nav')).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(page.locator('#lp-nav')).toBeHidden()
    await burger.click()
    await page.locator('#lp-nav .lp-top__link').nth(1).click()
    await expect(page.locator('#lp-nav')).toBeHidden()
    await expect(page.locator('#paths')).toBeInViewport()
  })
})

test.describe('landing — accessibility', () => {
  test('the keyboard reaches the skip link, the header controls and the main content', async ({
    page,
  }, testInfo) => {
    await open(page, testInfo, { reduced: true })
    await page.keyboard.press('Tab')
    const skip = page.locator('.lp-skip')
    await expect(skip).toBeFocused()
    await expect(skip).toBeInViewport()
    await page.keyboard.press('Enter')
    await expect(page.locator('#lp-main')).toBeFocused()
    await page.locator('.lp-theme-switch').focus()
    await expect(page.locator('.lp-theme-switch')).toHaveAttribute('role', 'switch')
    const outline = await page
      .locator('.lp-theme-switch')
      .evaluate((node) => getComputedStyle(node).outlineStyle)
    expect(outline).not.toBe('none')
  })

  test('landmarks, headings and images are described', async ({ page }, testInfo) => {
    await open(page, testInfo, { reduced: true })
    await expect(page.locator('header.lp-top')).toHaveCount(1)
    await expect(page.locator('main#lp-main')).toHaveCount(1)
    await expect(page.locator('footer.lp-foot')).toHaveCount(1)
    await expect(page.locator('h1')).toHaveCount(1)
    await expect(page.locator('#lp-main img:not([alt])')).toHaveCount(0)
    const nameless = await page
      .locator('.lp button')
      .evaluateAll(
        (buttons) =>
          buttons.filter(
            (button) =>
              !(
                button.getAttribute('aria-label') ||
                button.getAttribute('aria-labelledby') ||
                button.labels?.length ||
                button.innerText.trim()
              ),
          ).length,
      )
    expect(nameless).toBe(0)
  })

  for (const theme of THEMES) {
    test(`text meets AA contrast in the ${theme} theme`, async ({ page }, testInfo) => {
      await open(page, testInfo, { theme, reduced: true })
      for (const selector of [
        '#lp-hero-title',
        '.lp-hero__sub',
        '.lp-hero__copy .lp-btn--fill',
        '.lp-stat span',
        '.lp-head__lede',
        '#paths .lp-path__brief',
        '#paths .lp-path:not(.lp-path--featured) .lp-path__cost-label',
        '.lp-jr__text p',
        '.lp-lv__hint',
        '.lp-kit__steps .lp-kit__step-text > span',
        '.lp-lenses__note',
        '.lp-contact__lede',
        '.lp-foot__nav a',
      ]) {
        await page.locator(selector).first().scrollIntoViewIfNeeded()
        expect(await contrastOf(page, selector), selector).toBeGreaterThanOrEqual(AA_TEXT)
      }
    })
  }

  test('reduced motion shows the whole page still, with no loader or animation', async ({
    page,
  }, testInfo) => {
    await open(page, testInfo, { reduced: true })
    await expect(page.locator('.lp-loader')).toHaveCount(0)
    await expect(page.locator('.lp-hero')).toHaveCSS('position', 'relative')
    await expect(page.locator('.lp-hero__seals')).toHaveCSS('opacity', '1')
    await revealSection(page, 'about')
    await expect(page.locator('.lp-manifesto__word:not(.is-lit)')).toHaveCount(0)
    await expect(page.locator('.is-reveal-pending')).toHaveCount(0)
    const moving = await page
      .locator('.lp-final__trophy')
      .evaluate((node) => Number.parseFloat(getComputedStyle(node).animationDuration) < 0.01)
    expect(moving).toBe(true)
  })
})

test.describe('landing — widths and themes', () => {
  for (const [name, viewport] of Object.entries(VIEWPORTS)) {
    const wide = viewport.width >= 1100
    for (const theme of THEMES) {
      test(`${name} ${theme}: no horizontal scroll and the right navigation`, async ({
        page,
      }, testInfo) => {
        await page.setViewportSize(viewport)
        await open(page, testInfo, { theme, reduced: true })
        await expect(page.locator('html')).toHaveAttribute('data-theme', theme)
        expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0)
        const burger = page.locator('.lp-top__burger')
        await expect(burger).toBeVisible({ visible: !wide })
        await page.screenshot({
          path: `${SHOTS}/${name}-${theme}-${suffixOf(testInfo)}.png`,
          fullPage: true,
        })
      })
    }
  }
})

const FIXED_OVERLAYS = '.lp-top, .lp-skip, .lp-assist-btn { visibility: hidden !important; }'

async function pageDataReady(page) {
  await expect(page.locator('#model .lp-model-criteria[aria-busy]')).toHaveCount(0)
  await page.addStyleTag({ content: FIXED_OVERLAYS })
}

const SECTION_DATA_READY = {
  paths: (page) => expect(page.locator('#paths .lp-path__vat').first()).toBeVisible(),
  levels: (page) => expect(page.locator('#levels .lp-lv__tiers button')).toHaveCount(5),
}

async function imagesLoaded(page, id) {
  return page
    .locator(`#${id} img`)
    .evaluateAll((images) => images.every((image) => image.complete && image.naturalWidth > 0))
}

async function settleSection(page, id) {
  await revealSection(page, id)
  await SECTION_DATA_READY[id]?.(page)
  await expect.poll(() => imagesLoaded(page, id)).toBe(true)
  await page.evaluate(() => document.fonts.ready)
}

// The baselines are rendered on macOS with the licensed Arabic font. Linux CI, and any checkout
// without the font, render text differently, so only that setup compares them.
const HAS_SCREENSHOT_BASELINES =
  process.platform === 'darwin' && licensedFontsPresent(process.cwd())

test.describe('landing — screenshot baselines', () => {
  // eslint-disable-next-line playwright/no-skipped-test -- a conditional skip: baselines need macOS and the font
  test.skip(
    !HAS_SCREENSHOT_BASELINES,
    'The landing baselines need macOS and the licensed Arabic font.',
  )

  for (const [name, viewport] of Object.entries(VIEWPORTS)) {
    for (const theme of THEMES) {
      test(`${name} ${theme} matches its baselines`, async ({ page }, testInfo) => {
        await page.setViewportSize(viewport)
        await open(page, testInfo, { theme, reduced: true })
        await page.evaluate(() => document.fonts.ready)
        const options = { animations: 'disabled', maxDiffPixelRatio: 0.01 }
        await expect(page).toHaveScreenshot(`landing-${name}-${theme}-top.png`, options)
        await pageDataReady(page)
        for (const id of ['paths', 'levels', 'kit']) {
          await settleSection(page, id)
          await expect(page.locator(`#${id}`)).toHaveScreenshot(
            `landing-${name}-${theme}-${id}.png`,
            options,
          )
        }
      })
    }
  }
})
