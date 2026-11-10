import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedSignedIn, seedScenario } from './support/session'
import { settleFonts } from './support/screenshots'
import { chooseNamed } from './support/dropdown'

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
    ['system-admin', USERS.systemAdmin, 'system_admin'],
    ['follower', { id: 28, roles: ['request_follower'] }, 'request_follower'],
    ['filtering officer', USERS.follower, 'filtering_officer'],
    ['assessor', USERS.assessor, 'assessor'],
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
      await expect(page.locator('.action-center__title')).toBeVisible()
      expect(errors).toEqual([])
    })
  }

  test('the committee board joins the boards of an appointed assessor', async ({
    page,
  }, testInfo) => {
    await seedScenario(page, 'committee')
    await signIn(page, testInfo, { id: 27, roles: ['assessor'] })
    await open(page, '/dashboard?board=committee_assessor', '[data-board="committee_assessor"]')
    await expect(page.locator('[data-widget="cycles"] tbody tr')).toHaveCount(1)
  })

  test('a multi-role account switches boards without a role switcher and keeps one Action Center', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.multiRole)
    await open(page, '/dashboard', '[data-board="request_follower"]')
    const links = page.locator('.dashboard-board-nav__link')
    await expect(links).toHaveCount(2)
    await expect(links.first()).toHaveAttribute('aria-current', 'page')
    const items = await page.locator('.action-center__item').count()
    await links.nth(1).click()
    await expect(page).toHaveURL(/board=assessor/)
    await expect(page.locator('[data-board="assessor"]')).toBeVisible()
    await expect(page.locator('.action-center__item')).toHaveCount(items)
    await page.locator('.action-center__link').first().click()
    await expect(page).toHaveURL(/\/admin\/requests/)
  })

  test('the Action Center deep link opens the new contact messages', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/dashboard', '[data-action="answer_contact_messages"]')
    await page.locator('[data-action="answer_contact_messages"] a').click()
    await expect(page).toHaveURL(/\/admin\/contact-messages\?status=new$/)
    await expect(page.locator(ROWS)).toHaveCount(1)
  })
})

test.describe('notification types catalogue', () => {
  test('is read-only and filters by module', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/settings/notifications', ROWS)
    await expect(page.locator('.notification-catalogue textarea')).toHaveCount(0)
    await page.locator('.notification-catalogue__filters .dropdown-filter__trigger').first().click()
    await page.getByRole('option', { name: text(testInfo, 'Contact us', 'تواصل معنا') }).click()
    await expect(page.locator(ROWS).first()).toContainText('CNT-')
    await expect(page.locator(`${ROWS}:not(:has-text("CNT-"))`)).toHaveCount(0)
  })
})

test.describe('contact inbox', () => {
  test('the handler assigns, replies, adds a note, closes and reopens a message', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/contact-messages', ROWS)
    await expect(
      page.locator('.sidebar__item', {
        hasText: text(testInfo, 'Contact messages', 'رسائل تواصل معنا'),
      }),
    ).toBeVisible()
    await page.locator(`${ROWS} .contact-inbox__reference`).first().click()
    await expect(page).toHaveURL(new RegExp(`/admin/contact-messages/${NEW_MESSAGE}$`))
    await chooseNamed(page.locator('.contact-assign'), 'assigneeId', '31')
    await page.locator('.contact-assign .btn').click()
    await expect(page.locator('.contact-thread__meta .badge')).toContainText(
      text(testInfo, 'In progress', 'قيد المعالجة'),
    )
    await page
      .locator('.contact-composer textarea')
      .fill('نرفق لكم دليل الشواهد المعتمد للمعيار الفرعي المذكور.')
    await page.locator('.contact-composer button[type="submit"]').click()
    await expect(page.locator('[data-entry-kind="reply"]')).toHaveCount(1)
    await page.locator('.contact-composer input[value="note"]').check()
    await page.locator('.contact-composer textarea').fill('أُرسل الدليل؛ نتابع بعد أسبوع.')
    await page.locator('.contact-composer button[type="submit"]').click()
    await expect(page.locator('[data-entry-kind="note"]')).toHaveCount(1)
    await page
      .getByRole('button', { name: text(testInfo, 'Close the message', 'إغلاق الرسالة') })
      .click()
    await expect(page.locator('.contact-thread__closed')).toBeVisible()
    await page
      .getByRole('button', { name: text(testInfo, 'Reopen the message', 'إعادة فتح الرسالة') })
      .click()
    await expect(page.locator('.contact-thread__closed')).toHaveCount(0)
    expect(errors).toEqual([])
  })

  test('the Content Officer handles messages but a finance officer cannot open the inbox', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.content)
    await open(page, '/admin/contact-messages', ROWS)
    await signIn(page, testInfo, USERS.finance)
    await page.goto('/admin/contact-messages')
    await expect(page).toHaveURL(/\/403/)
  })

  test('filters by linked senders and searches', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/contact-messages', ROWS)
    await page.locator('.toolbar__search input').fill('البيان')
    await expect(page.locator(ROWS)).toHaveCount(1)
    await expect(page.locator(ROWS).first()).toContainText('CNT-2026-000051')
  })
})

test.describe('contact retention and privacy tools', () => {
  test('the System Administrator saves the retention period', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/settings/contact-messages', 'input[name="retentionMonths"]')
    await page.locator('input[name="retentionMonths"]').fill('12')
    await page.locator('.settings-section__footer button[type="submit"]').click()
    await expect(page.locator('.toast').first()).toContainText(text(testInfo, 'saved', 'حُفظت'))
  })

  test('exports and erases the contact messages of an address', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/privacy-requests', '.contact-privacy')
    await page.locator('.contact-privacy input[type="email"]').fill('finance@albayan.example.sa')
    await page.locator('.contact-privacy textarea').fill('طلب صاحب البيانات رقم PR-2026-0012')
    await page.locator('.contact-privacy button[type="submit"]').click()
    await expect(page.locator('.contact-privacy__result')).toContainText('CNT-2026-000051')
    await page
      .getByRole('button', { name: text(testInfo, 'Delete the messages', 'حذف الرسائل') })
      .click()
    await page.locator('.modal .btn--danger').click()
    await expect(page.locator('.contact-privacy__result')).toContainText(
      text(testInfo, 'were deleted', 'حُذفت'),
    )
  })
})

test.describe('staff certificate and review completion', () => {
  test('the System Administrator sees the certificate history and re-issues the certificate', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await open(page, '/admin/requests/9104', '[data-certificate="current"]')
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
    await page.locator('.review-actions .btn--primary').click()
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
  ['dashboard', '/dashboard', '[data-board="system_admin"]', USERS.systemAdmin],
  ['dashboard-follower', '/dashboard', '[data-board="request_follower"]', USERS.multiRole],
  ['notification-types', '/admin/settings/notifications', ROWS, USERS.systemAdmin],
  ['contact-inbox', '/admin/contact-messages', ROWS, USERS.systemAdmin],
  [
    'contact-thread',
    `/admin/contact-messages/7d0c3c1e-4a51-4f0e-9a51-1a2b3c4d5e02`,
    '.contact-entries__item',
    USERS.systemAdmin,
  ],
  [
    'contact-settings',
    '/admin/settings/contact-messages',
    'input[name="retentionMonths"]',
    USERS.systemAdmin,
  ],
  ['privacy-contact', '/admin/privacy-requests', '.contact-privacy', USERS.systemAdmin],
  [
    'request-certificate',
    '/admin/requests/9104',
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
