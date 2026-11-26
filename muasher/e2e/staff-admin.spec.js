import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedSignedIn, seedScenario } from './support/session'
import { settleFonts } from './support/screenshots'
import { chooseNamed } from './support/dropdown'
import { searchList } from './support/listFilters'
import { scoreAndSaveEveryItem } from './support/reviewWork'

const SHOTS = 'e2e/__screenshots__/37d'
const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  mobile: { width: 390, height: 844 },
}
const USERS = {
  systemAdmin: {
    id: 1,
    roles: ['system_admin'],
    name: { ar: 'ريم بنت خالد الحربي', en: 'Reem Al-Harbi' },
  },
  follower: { id: 8, roles: ['request_follower', 'filtering_officer'] },
  multiRole: { id: 8, roles: ['request_follower', 'assessor'] },
  assessor: { id: 10, roles: ['assessor'] },
  content: { id: 31, roles: ['content_officer'] },
  finance: { id: 41, roles: ['finance_officer'] },
  secretary: { id: 51, roles: ['award_secretary'] },
  criteria: { id: 61, roles: ['criteria_officer'] },
  superAdmin: {
    id: 900,
    roles: [],
    superAdmin: true,
    name: { ar: 'مدير النظام الرئيسي', en: 'Super Admin' },
  },
}
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'
const ROWS = '.table:not(.table--loading) tbody .table__row'
// A message in the inbox: a table row link with no message open, a list item beside an open one.
const INBOX_ITEMS = '[data-message-id]'
// The whole row of a message, with its reference: a table row or a list item.
const INBOX_ROWS = '.table__row, .contact-list__item'
const NEW_MESSAGE = '7d0c3c1e-4a51-4f0e-9a51-1a2b3c4d5e01'
const IGNORED_CONSOLE =
  /Failed to load resource|40\d|50\d|\[vite\]|\[MSW\]|Download the Vue Devtools/i

mkdirSync(SHOTS, { recursive: true })

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function text(testInfo, en, ar) {
  return isEn(testInfo) ? en : ar
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function signIn(page, testInfo, user, theme = 'light') {
  return seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar', theme })
}

function trackConsole(page) {
  const errors = []
  page.on('console', (message) => {
    if (message.type() === 'error' && !IGNORED_CONSOLE.test(message.text()))
      errors.push(message.text())
  })
  page.on('pageerror', (error) => errors.push(error.message))
  return errors
}

async function open(page, path, ready) {
  await page.goto(path)
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await expect(page.locator(ready).first()).toBeVisible({ timeout: 10000 })
}

async function noHorizontalOverflow(page) {
  await expect
    .poll(() =>
      page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
      ),
    )
    .toBeLessThanOrEqual(1)
}

async function capture(page, name) {
  await settleFonts(page)
  await page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: true })
}

test.describe('staff dashboards on the board contract', () => {
  for (const [key, user, board] of [
    ['follower', { id: 28, roles: ['request_follower'] }, 'request_follower'],
    ['filtering officer', USERS.follower, 'filtering_officer'],
    ['finance', USERS.finance, 'finance_officer'],
    ['content', USERS.content, 'content_officer'],
    ['secretary', USERS.secretary, 'award_secretary'],
    ['criteria', USERS.criteria, 'criteria_officer'],
  ]) {
    test(`the ${key} lands on its board with cards and widgets`, async ({ page }, testInfo) => {
      const errors = trackConsole(page)
      await signIn(page, testInfo, user)
      await open(page, '/dashboard', `[data-board="${board}"]`)
      await expect(page.locator('.dashboard-cards__item').first()).toBeVisible()
      await expect(page.locator('.dashboard-widget').first()).toBeVisible()
      await expect(page.locator('.action-center')).toHaveCount(0)
      expect(errors).toEqual([])
    })
  }

  test('the System Administrator lands on the platform overview in place of a board', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/dashboard', '[data-testid="executive-overview"]')
    await expect(page.locator('.overview-cards__card').first()).toBeVisible()
    await expect(page.locator('.overview-panel').first()).toBeVisible()
    await expect(page.locator('.action-center')).toHaveCount(0)
    await expect(page.locator('[data-board="system_admin"]')).toHaveCount(0)
    expect(errors).toEqual([])
  })

  test('the committee board joins the boards of an appointed assessor', async ({
    page,
  }, testInfo) => {
    await seedScenario(page, 'committee')
    await signIn(page, testInfo, { id: 27, roles: ['assessor'] })
    await open(page, '/dashboard?board=committee_assessor', '[data-board="committee_assessor"]')
    await expect(page.locator('[data-widget="cycles"] tbody tr')).toHaveCount(1)
  })

  test('a multi-role account switches boards without a role switcher; the pending work stays on the sidebar', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.multiRole)
    await open(page, '/dashboard', '[data-board="request_follower"]')
    const links = page.locator('.dashboard-board-nav__link')
    await expect(links).toHaveCount(2)
    await expect(links.first()).toHaveAttribute('aria-current', 'page')
    const counts = await page.locator('.sidebar__count').count()
    await links.nth(1).click()
    await expect(page).toHaveURL(/board=assessor/)
    await expect(page.locator('[data-testid="assessor-overview"]')).toBeVisible()
    await expect(page.locator('.sidebar__count')).toHaveCount(counts)
  })

  test('the pending contact messages count on the inbox item, which opens the inbox', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/dashboard', '[data-testid="executive-overview"]')
    const inbox = page.locator('.sidebar__item[href="/admin/contact-messages"]')
    await expect(inbox.locator('.sidebar__count')).toHaveText('2')
    await inbox.click()
    await expect(page).toHaveURL(/\/admin\/contact-messages$/)
    await expect(page.locator(INBOX_ITEMS).first()).toBeVisible()
  })
})

const GROUPS = '.notification-groups'

function switchTrack(page, name) {
  return page.locator(`.toggle-switch:has(input[name="${name}"]) .toggle-switch__track`)
}

async function openGroup(page, testInfo, en, ar) {
  await page.locator('.accordion__trigger', { hasText: text(testInfo, en, ar) }).click()
}

async function confirmModal(page) {
  await page.locator('.modal__panel .btn--primary, .modal__panel .btn--danger').last().click()
}

test.describe('notification types catalogue', () => {
  test('groups every type by module and finds one by code', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/settings/notifications', GROUPS)
    await expect(page.locator('.notification-groups__title').first()).toBeVisible()
    await searchList(page, 'CNT-01')
    await expect(page.locator('.notification-matrix__row')).toHaveCount(1)
    await expect(page.locator('.notification-matrix__row')).toContainText('CNT-01')
  })

  test('a whole group is switched off after confirmation and its core type stays on', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/settings/notifications', GROUPS)
    await openGroup(page, testInfo, 'Registration and organization profile', 'التسجيل وملف الجهة')
    await page
      .locator('.notification-matrix__head .notification-group-actions button')
      .nth(1)
      .click()
    await confirmModal(page)
    await expect(page.locator('input[name="REG-01-beneficiary-in_app"]')).not.toBeChecked()
    await expect(page.locator('input[name="REG-02-beneficiary-email"]')).toBeChecked()
    await expect(page.locator('input[name="REG-02-beneficiary-email"]')).toBeDisabled()
  })

  test('the by-channel view counts the types each channel sends', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/settings/notifications?view=byChannel', GROUPS)
    await expect(page.locator('.notification-catalogue__channels [role="tab"]')).toHaveCount(3)
    await expect(
      page.locator('.notification-catalogue__channels [role="tab"]').first(),
    ).toContainText('/')
  })
})

test.describe('admin lists at 1440px', () => {
  test('the users and entities tables show their data in full without moving the page sideways', async ({
    page,
  }, testInfo) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await signIn(page, testInfo, USERS.systemAdmin)
    for (const path of ['/admin/settings/users', '/admin/entities']) {
      await open(page, path, ROWS)
      const pageOverflow = await page.evaluate(
        () => document.documentElement.scrollWidth - window.innerWidth,
      )
      expect(pageOverflow).toBeLessThanOrEqual(0)
      const cutOff = await page
        .locator(`${ROWS} .table__cell:not(.table__cell--note) .table__value`)
        .evaluateAll(
          (values) => values.filter((value) => value.scrollWidth > value.clientWidth + 1).length,
        )
      expect(cutOff).toBe(0)
    }
    await open(page, '/admin/settings/users', ROWS)
    await expect(
      page.locator(`${ROWS} .users-page__identity .users-page__email`).first(),
    ).toBeVisible()
    await expect(page.locator(`${ROWS} .users-page__state .table__meta`).first()).toBeVisible()
  })
})

test.describe('notification texts', () => {
  test('the System Administrator edits a text with a variable, saves it and restores the original', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/settings/notifications', GROUPS)
    await openGroup(page, testInfo, 'Registration and organization profile', 'التسجيل وملف الجهة')
    await page.locator('a[href="/admin/settings/notifications/REG-01"]').click()
    await expect(page).toHaveURL(/\/admin\/settings\/notifications\/REG-01$/)
    await expect(
      page.locator('.notification-texts__audiences > .tabs__list [role="tab"]'),
    ).toHaveCount(2)
    const title = page.locator('textarea[name="entities.REG-01.entity-title-ar"]')
    await title.fill('مرحبًا')
    await page.locator('.notification-variables__chip').first().click()
    await expect(title).toHaveValue('مرحبًا :entity')
    await page.locator('.notification-text button[type="submit"]').click()
    await expect(page.locator('.notification-text__bar')).toContainText(
      text(testInfo, 'Edited', 'معدّل'),
    )
    await page
      .getByRole('button', {
        name: text(testInfo, 'Restore the original text', 'استعادة النص الأصلي'),
      })
      .click()
    await confirmModal(page)
    await expect(page.locator('.notification-text__bar')).not.toContainText(
      text(testInfo, 'Edited', 'معدّل'),
    )
    await expect(title).not.toHaveValue('مرحبًا :entity')
  })

  test('e-mail is switched off for management only and stays on for the beneficiary', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/settings/notifications', GROUPS)
    await openGroup(page, testInfo, 'Registration and organization profile', 'التسجيل وملف الجهة')
    await expect(page.locator('input[name="REG-01-management-email"]')).toBeChecked()
    await switchTrack(page, 'REG-01-management-email').click()
    await expect(page.locator('input[name="REG-01-management-email"]')).not.toBeChecked()
    await expect(page.locator('input[name="REG-01-beneficiary-email"]')).toBeChecked()
    // In-app navigation: a reload would restart the mock API and forget the choice just made.
    await page.locator('a.notification-edit-link[href*="/notifications/REG-01"]').first().click()
    await page.locator('.notification-text__channels [role="tab"]').nth(1).click()
    await expect(page.locator('input[name="entities.REG-01.entity-email"]')).toBeChecked()
    await page.locator('.notification-texts__audiences > .tabs__list [role="tab"]').nth(1).click()
    await page.locator('.notification-text__channels [role="tab"]').nth(1).click()
    await expect(page.locator('input[name="entities.REG-01.staff-email"]')).not.toBeChecked()
  })

  test('the System Administrator switches SMS on and writes the text message', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/settings/notifications/REG-01?channel=sms', '.notification-text')
    await switchTrack(page, 'entities.REG-01.entity-sms').click()
    await expect(page.locator('input[name="entities.REG-01.entity-sms"]')).toBeChecked()
    const arabic = page.locator('textarea[name="entities.REG-01.entity-sms-ar"]')
    await arabic.fill('سجّلت')
    await page.locator('.notification-variables__chip').first().click()
    await expect(page.locator('.notification-sms-counter')).toContainText('/70')
    await page.locator('.notification-view-switch__option').nth(1).click()
    await page.locator('textarea[name="entities.REG-01.entity-sms-en"]').fill('Registered')
    await page.locator('.notification-text button[type="submit"]').click()
    await expect(page.locator('.notification-text__sms-approval')).toContainText(
      text(testInfo, 'No approval needed', 'لا يتطلب اعتمادًا'),
    )
    await page.locator('.notification-view-switch__option').first().click()
    await expect(arabic).toHaveValue('سجّلت :entity')
  })

  test('a core notification is always sent and keeps its button', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/settings/notifications/USR-01', '.notification-text')
    await expect(page.locator('.notification-texts__core-banner')).toBeVisible()
    await expect(page.locator('.notification-text input[role="switch"]')).toBeDisabled()
    await expect(page.locator('textarea[name="staff_invited-action-ar"]')).toHaveCount(0)
    await expect(page.locator('.notification-channel-fields__fixed')).toBeVisible()
  })

  test('a core notification keeps its required variables', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/settings/notifications/USR-01', '.notification-text')
    await expect(page.locator('.notification-variables__chip.is-required')).toHaveCount(1)
    await page.locator('textarea[name="staff_invited-expiry-ar"]').fill('صالحة لمدة قصيرة')
    await page.locator('.notification-text button[type="submit"]').click()
    await expect(page.locator('textarea[name="staff_invited-expiry-ar"]')).toHaveAttribute(
      'aria-invalid',
      'true',
    )
  })
})

// «dir=auto» gives each text the direction of its first strong letter, whatever the page's:
// Arabic reads right to left and English left to right, and the matches include Arabic.
async function expectOwnDirection(page, selector) {
  const texts = await page.locator(selector).evaluateAll((nodes) =>
    nodes.map((node) => ({
      direction: getComputedStyle(node).direction,
      isArabic: /^[^A-Za-z\u0600-\u06FF]*[\u0600-\u06FF]/.test(node.textContent),
    })),
  )
  expect(texts.some((text) => text.isArabic)).toBe(true)
  for (const text of texts) expect(text.direction).toBe(text.isArabic ? 'rtl' : 'ltr')
}

// The status dot is the badge's ::before, so the wider gap sits on the text's start side.
async function expectDotAtStart(badge) {
  const gaps = await badge.evaluate((node) => {
    const range = document.createRange()
    range.selectNodeContents(node)
    const text = range.getBoundingClientRect()
    const box = node.getBoundingClientRect()
    const left = text.left - box.left
    const right = box.right - text.right
    const isRtl = getComputedStyle(node).direction === 'rtl'
    return { start: isRtl ? right : left, end: isRtl ? left : right }
  })
  expect(gaps.start).toBeGreaterThan(gaps.end)
}

test.describe('contact inbox', () => {
  test('the handler assigns, replies, adds a note, closes and reopens a message', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/contact-messages', INBOX_ITEMS)
    await expect(
      page.locator('.sidebar__item', {
        hasText: text(testInfo, 'Inbox', 'صندوق الرسائل'),
      }),
    ).toBeVisible()
    await page.locator(`${INBOX_ITEMS}[data-message-id="${NEW_MESSAGE}"]`).click()
    await expect(page).toHaveURL(new RegExp(`/admin/contact-messages/${NEW_MESSAGE}$`))
    await chooseNamed(page.locator('.contact-assign'), 'assigneeId', '31')
    await page.locator('.contact-assign .btn').click()
    await expect(page.locator('.contact-controls .contact-status')).toContainText(
      text(testInfo, 'Assigned', 'مسندة'),
    )
    await page
      .locator('.contact-composer textarea')
      .fill('نرفق لكم دليل الشواهد المعتمد للمعيار الفرعي المذكور.')
    await page.locator('.contact-composer button[type="submit"]').click()
    await expect(page.locator('[data-entry-kind="reply"]')).toHaveCount(1)
    await expect(page.locator('.contact-controls .contact-status')).toContainText(
      text(testInfo, 'Replied', 'تم الرد'),
    )
    await page.locator('.contact-composer__mode--note').click()
    await page.locator('.contact-composer textarea').fill('أُرسل الدليل؛ نتابع بعد أسبوع.')
    await page.locator('.contact-composer button[type="submit"]').click()
    await expect(page.locator('[data-entry-kind="note"]')).toHaveCount(1)
    await page
      .getByRole('button', { name: text(testInfo, 'Close the message', 'إغلاق الرسالة') })
      .click()
    await expect(page.locator('.contact-panel__closed')).toBeVisible()
    await expect(page.locator('.contact-composer')).toHaveCount(0)
    await page
      .getByRole('button', { name: text(testInfo, 'Reopen the message', 'إعادة فتح الرسالة') })
      .click()
    await expect(page.locator('.contact-panel__closed')).toHaveCount(0)
    await expect(page).toHaveURL(new RegExp(`/admin/contact-messages/${NEW_MESSAGE}$`))
    await page.keyboard.press('Escape')
    await expect(page).toHaveURL(/\/admin\/contact-messages$/)
    await expect(page.locator('.contact-panel')).toHaveCount(0)
    await expect(page.locator(`${INBOX_ITEMS}[data-message-id="${NEW_MESSAGE}"]`)).toBeVisible()
    expect(errors).toEqual([])
  })

  for (const [size, viewport] of Object.entries(VIEWPORTS)) {
    test(`text written by people keeps its own direction in either interface (${size})`, async ({
      page,
    }, testInfo) => {
      await page.setViewportSize(viewport)
      await signIn(page, testInfo, USERS.systemAdmin)
      await open(page, '/admin/contact-messages', INBOX_ITEMS)
      await expectOwnDirection(page, '.contact-table__name, .contact-list__sender')
      await expectOwnDirection(page, '.contact-table__excerpt, .contact-list__excerpt')
      await page.locator(`${INBOX_ITEMS}[data-message-id="${NEW_MESSAGE}"]`).click()
      await expect(page.locator('.contact-entries__item').first()).toBeVisible()
      await expectOwnDirection(page, '.contact-panel__title')
      await expectOwnDirection(page, '.contact-panel__name')
      await expectOwnDirection(page, '.contact-entries__body')
      await expectOwnDirection(page, '.contact-entries__author')
      await expectDotAtStart(page.locator('.contact-controls .contact-status'))
      await noHorizontalOverflow(page)
    })
  }

  test('the Content Officer handles messages but a finance officer cannot open the inbox', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.content)
    await open(page, '/admin/contact-messages', INBOX_ITEMS)
    await signIn(page, testInfo, USERS.finance)
    await page.goto('/admin/contact-messages')
    await expect(page).toHaveURL(/\/403/)
  })

  test('filters by linked senders and searches', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/contact-messages', INBOX_ITEMS)
    await searchList(page, 'البيان')
    await expect(page.locator(INBOX_ROWS)).toHaveCount(1)
    await expect(page.locator(INBOX_ROWS).first()).toContainText('CNT-2026-000051')
  })
})

test.describe('contact retention', () => {
  test('the System Administrator saves the retention period', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/settings/general', 'input[name="retentionMonths"]')
    await page.locator('input[name="retentionMonths"]').fill('12')
    await page.locator('[data-testid="contact-retention-settings"] button[type="submit"]').click()
    await expect(page.locator('.toast').first()).toContainText(text(testInfo, 'saved', 'حُفظت'))
  })
})

test.describe('staff certificate and review completion', () => {
  test('the System Administrator sees the certificate history and re-issues the certificate', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/requests/9104?tab=certificate', '[data-certificate="current"]')
    await expect(page.locator('.request-certificate__history-item')).toHaveCount(1)
    await page
      .getByRole('button', { name: text(testInfo, 'Re-issue', 'إعادة الإصدار') })
      .first()
      .click()
    await page.locator('.modal textarea').fill('إعادة إصدار بعد تحديث الاسم الرسمي للجهة.')
    await page.locator('.modal button[type="submit"]').click()
    await expect(page.locator('.request-certificate__history-item')).toHaveCount(2)
    await expect(page.locator('[data-certificate="current"]')).toContainText('CRT-2026-0000')
  })

  test('completing an accredited review asks one question and offers no certificate choice', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.assessor)
    await open(page, '/admin/requests/9108/review', '.review-header')
    // «إكمال التقييم» shows only once every item is answered (operator, 2026-11-22).
    await scoreAndSaveEveryItem(page)
    await page.locator('.station-row__button, .review-strip__step').last().click()
    await page.locator('[data-testid="review-complete"]').click()
    const dialog = page.getByRole('alertdialog')
    await expect(dialog.locator('.modal__title')).toHaveText(
      text(testInfo, 'Complete the assessment?', 'إكمال التقييم؟'),
    )
    await expect(dialog.locator('input[type="checkbox"]')).toHaveCount(0)
    await expect(dialog.locator('.modal__message')).toBeVisible()
    await dialog.getByRole('button', { name: text(testInfo, 'Cancel', 'إلغاء') }).click()
    await expect(dialog).toHaveCount(0)
  })
})

test.describe('legal texts management', () => {
  test('the Content Officer drafts, previews and publishes a new privacy version', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.content)
    await open(page, '/admin/settings/homepage', '.tabs__tab')
    await page
      .getByRole('tab', { name: text(testInfo, 'Privacy policy', 'سياسة الخصوصية') })
      .click()
    await expect(page.locator('[data-legal-kind="privacy"] tbody tr')).toHaveCount(2)
    await page
      .getByRole('button', {
        name: text(testInfo, 'New draft from the current text', 'مسودة جديدة من النص الحالي'),
      })
      .click()
    await page.locator('input[name="version"]').fill('2026-12')
    await page
      .locator('textarea[name="body.en"]')
      .fill('## 1. Introduction\n\n- visitors of the platform;\n- registered entities.')
    await expect(page.locator('.legal-preview[lang="en"] h4')).toHaveText('1. Introduction')
    await expect(page.locator('.legal-preview[lang="en"] li')).toHaveCount(2)
    await page
      .getByRole('button', { name: text(testInfo, 'Create the draft', 'إنشاء المسودة') })
      .click()
    await expect(
      page.getByRole('button', { name: text(testInfo, 'Publish the version', 'نشر الإصدار') }),
    ).toBeEnabled()
    await page
      .getByRole('button', { name: text(testInfo, 'Publish the version', 'نشر الإصدار') })
      .click()
    await page.locator('.modal .btn--primary').click()
    await expect(page.locator('.legal-viewer')).toBeVisible()
    await expect(page.locator('.legal-viewer')).toContainText(
      text(testInfo, 'Current version', 'الإصدار الحالي'),
    )
  })

  test('the accessibility statement is edited in the CMS page editor', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.content)
    await open(page, '/admin/settings/homepage', '.tabs__tab')
    await page
      .getByRole('tab', { name: text(testInfo, 'Accessibility statement', 'بيان إمكانية الوصول') })
      .click()
    await expect(page.locator('.cms-section-row').first()).toBeVisible()
  })
})

test.describe('audit log', () => {
  test('the System Administrator filters, opens an entry and exports the CSV', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/audit-log', ROWS)
    await page.locator('input[name="audit-event"]').fill('entity.updated')
    await page.locator('.audit-log__filters button[type="submit"]').click()
    await expect(page.locator(ROWS)).toHaveCount(1)
    await page.locator(`${ROWS} button`).click()
    await expect(page.locator('.audit-entry__json').last()).toContainText('deactivated')
    await page
      .locator('.modal')
      .getByRole('button', { name: text(testInfo, 'Close', 'إغلاق') })
      .last()
      .click()
    const download = page.waitForEvent('download')
    await page.getByRole('button', { name: text(testInfo, 'Export CSV', 'تصدير CSV') }).click()
    await page.locator('.modal button[type="submit"]').click()
    expect((await download).suggestedFilename()).toMatch(/^audit-log-.*\.csv$/)
  })

  test('another role gets the forbidden page', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.content)
    await page.goto('/admin/audit-log')
    await expect(page).toHaveURL(/\/403/)
  })
})

const CAPTURES = [
  ['dashboard', '/dashboard', '[data-testid="executive-overview"]', USERS.systemAdmin],
  ['dashboard-follower', '/dashboard', '[data-board="request_follower"]', USERS.multiRole],
  [
    'notification-types',
    '/admin/settings/notifications',
    '.notification-groups',
    USERS.systemAdmin,
  ],
  ['contact-inbox', '/admin/contact-messages', INBOX_ITEMS, USERS.systemAdmin],
  [
    'contact-thread',
    `/admin/contact-messages/7d0c3c1e-4a51-4f0e-9a51-1a2b3c4d5e02`,
    '.contact-entries__item',
    USERS.systemAdmin,
  ],
  [
    'contact-settings',
    '/admin/settings/general',
    'input[name="retentionMonths"]',
    USERS.systemAdmin,
  ],
  [
    'request-certificate',
    '/admin/requests/9104?tab=certificate',
    '[data-certificate="current"]',
    USERS.systemAdmin,
  ],
  ['audit-log', '/admin/audit-log', ROWS, USERS.systemAdmin],
]

test.describe('screens (ar / en, light / dark, desktop / mobile)', () => {
  for (const [name, path, ready, user] of CAPTURES) {
    for (const theme of ['light', 'dark']) {
      for (const [viewport, size] of Object.entries(VIEWPORTS)) {
        test(`${name} ${theme} ${viewport}`, async ({ page }, testInfo) => {
          await page.setViewportSize(size)
          await signIn(page, testInfo, user, theme)
          await open(page, path, ready)
          await expect(page.locator('main').first()).toBeVisible()
          await noHorizontalOverflow(page)
          await capture(page, `${name}-${theme}-${viewport}-${suffixOf(testInfo)}`)
        })
      }
    }
  }

  for (const theme of ['light', 'dark']) {
    for (const [viewport, size] of Object.entries(VIEWPORTS)) {
      test(`legal-editor ${theme} ${viewport}`, async ({ page }, testInfo) => {
        await page.setViewportSize(size)
        await signIn(page, testInfo, USERS.content, theme)
        await open(page, '/admin/settings/homepage', '.tabs__tab')
        await page
          .getByRole('tab', { name: text(testInfo, 'Terms of use', 'شروط الاستخدام') })
          .click()
        await page
          .getByRole('button', { name: text(testInfo, 'Continue the draft', 'متابعة المسودة') })
          .click()
        await expect(page.locator('.legal-preview').first()).toBeVisible()
        await noHorizontalOverflow(page)
        await capture(page, `legal-editor-${theme}-${viewport}-${suffixOf(testInfo)}`)
      })
    }
  }
})
