import { execFileSync } from 'node:child_process'
import { randomUUID } from 'node:crypto'
import { mkdirSync } from 'node:fs'
import process from 'node:process'
import { test, expect } from '@playwright/test'
import { assertMailLogsConfigured } from './support/mailbox'
import { STAFF, signInStaff } from './support/accounts'
import { registerEntity } from './support/entities'
import { EVIDENCE_PDF, apiOk, scoreSheetOf } from './support/api'
import {
  FOOTER_LEGAL_PROBES,
  GUEST_SESSION_PROBE,
  PAGE_NOT_PUBLISHED,
  SOURCES,
  watchApi,
} from './support/apiWatch'

const SHOTS = 'e2e/__screenshots__/37d/integration'
const RUN = Date.now().toString(36)
const ENTITY = {
  email: `staff.${RUN}@alwafa-association.example.sa`,
  password: `Wafa-${randomUUID()}`,
  nameAr: 'جمعية الوفاء للتنمية الأسرية',
  nameEn: 'Al-Wafa Family Development Association',
  person: 'نورة بنت فهد العتيبي',
}
const MESSAGE = 'نرجو توضيح الشواهد المطلوبة للمعيار الفرعي الخاص بقياس الأثر في التقييم المعتمد.'
const REPLY = 'شكرًا لتواصلكم؛ يكفي التقرير السنوي المعتمد مع مؤشرات قياس الأثر الموثقة.'
const CV_TEXT = 'نفذت الجمعية مبادرات موثقة لهذا المعيار مع مؤشرات قياس واضحة ومراجعة دورية.'
const ROWS = '.table:not(.table--loading) tbody .table__row'
const ALLOWED = [GUEST_SESSION_PROBE, PAGE_NOT_PUBLISHED, ...FOOTER_LEGAL_PROBES]
const sessions = {}
const flow = {}

test.describe.configure({ mode: 'serial' })
mkdirSync(SHOTS, { recursive: true })

async function openSession(browser, key) {
  const context = await browser.newContext({ locale: 'ar-SA', acceptDownloads: true })
  const page = await context.newPage()
  sessions[key] = { context, page, api: watchApi(page) }
}

function shot(page, name) {
  return page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: true })
}

async function completeForm(page, assessmentId) {
  for (let round = 0; round < 40; round += 1) {
    const { data } = await apiOk(page, 'GET', `/assessments/${assessmentId}/form`)
    const { missing_cv: missingCv, missing_attachments: missingFiles } = data.missing
    if (!missingCv.length && !missingFiles.length) return
    if (missingCv.length)
      await apiOk(page, 'PUT', `/assessments/${assessmentId}/cv`, {
        body: { cv: Object.fromEntries(missingCv.map((item) => [item.sub_criterion_id, CV_TEXT])) },
      })
    for (const item of missingFiles) {
      const fields = {
        sub_criterion_id: item.sub_criterion_id,
        ...(item.attachment_key ? { attachment_key: item.attachment_key } : {}),
      }
      await apiOk(page, 'POST', `/assessments/${assessmentId}/evidence`, {
        upload: {
          fields,
          content: EVIDENCE_PDF,
          name: `evidence-${item.code}.pdf`,
          type: 'application/pdf',
        },
      })
    }
    await new Promise((resolve) => setTimeout(resolve, 1500))
  }
  throw new Error('The accredited form did not become complete.')
}

function backendOnly(key, path) {
  expect(sessions[key].api.sourcesOf(path)).toEqual([SOURCES.BACKEND])
}

test.beforeAll(async ({ browser, request }) => {
  assertMailLogsConfigured()
  const health = await request.get('/api/v1/health', { headers: { Accept: 'application/json' } })
  expect(health.status()).toBe(200)
  for (const key of ['admin', 'content', 'follower', 'assessor', 'entity'])
    await openSession(browser, key)
})

test.afterAll(async () => {
  await Promise.all(Object.values(sessions).map(({ context }) => context.close()))
})

test('the staff sign in and a new entity registers', async () => {
  test.setTimeout(600000)
  await signInStaff(sessions.admin.page, STAFF.systemAdmin)
  await signInStaff(sessions.content.page, STAFF.contentOfficer)
  await signInStaff(sessions.follower.page, STAFF.requestsFollower)
  await signInStaff(sessions.assessor.page, STAFF.assessor)
  await registerEntity(sessions.entity.page, ENTITY)
  await expect(sessions.assessor.page.locator('.navbar--app')).toBeVisible()
})

test('the staff dashboards and the notification catalogue come from the backend', async () => {
  const { page } = sessions.admin
  await page.goto('/dashboard')
  await expect(page.locator('[data-board="system_admin"]')).toBeVisible()
  await expect(page.locator('.dashboard-cards__item').first()).toBeVisible()
  await shot(page, 'dashboard-system-admin')
  backendOnly('admin', /^\/api\/v1\/dashboard\/summary$/)
  await sessions.follower.page.goto('/dashboard')
  await expect(sessions.follower.page.locator('[data-board="request_follower"]')).toBeVisible()
  await page.goto('/admin/settings/notifications')
  await expect(page.locator(ROWS, { hasText: 'CNT-01' })).toHaveCount(1)
  backendOnly('admin', /^\/api\/v1\/settings\/notifications\/types$/)
})

test('a portal message is answered from the inbox and the reply reaches the portal', async () => {
  test.setTimeout(240000)
  const entity = sessions.entity.page
  const sent = await apiOk(entity, 'POST', '/contact-messages', {
    body: {
      enquiry_type: 'accredited_assessment',
      message: MESSAGE,
      privacy_consent: true,
      website: '',
    },
  })
  flow.messageId = sent.data.id
  flow.messageReference = sent.data.reference
  expect(flow.messageId).toBeTruthy()

  const content = sessions.content.page
  await content.goto('/admin/contact-messages?status=new')
  await expect(content.locator(ROWS, { hasText: flow.messageReference })).toHaveCount(1)
  await content
    .locator(`${ROWS} .contact-inbox__reference`, { hasText: flow.messageReference })
    .click()
  await expect(content.locator('.contact-entries__item').first()).toContainText(MESSAGE)
  await content.locator('.contact-composer textarea').fill(REPLY)
  await content.locator('.contact-composer button[type="submit"]').click()
  await expect(content.locator('[data-entry-kind="reply"]')).toContainText(REPLY)
  await content.locator('.contact-composer input[value="note"]').check()
  await content.locator('.contact-composer textarea').fill('أُرسل الرد؛ لا يلزم إجراء إضافي.')
  await content.locator('.contact-composer button[type="submit"]').click()
  await expect(content.locator('[data-entry-kind="note"]')).toHaveCount(1)
  await shot(content, 'contact-thread')
  backendOnly('content', /^\/api\/v1\/admin\/contact-messages/)

  await entity.goto(`/messages/${flow.messageId}`)
  await expect(entity.locator('.message-bubble--team')).toContainText(REPLY)
  await expect(entity.locator('.message-thread')).not.toContainText('لا يلزم إجراء إضافي')
  await shot(entity, 'portal-thread-reply')

  await content.getByRole('button', { name: 'إغلاق الرسالة' }).click()
  await expect(content.locator('.contact-thread__closed')).toBeVisible()
  await entity.reload()
  await expect(entity.locator('.message-thread')).toContainText('أغلق')
})

test('the contact retention and the contact privacy export work on the backend', async () => {
  const { page } = sessions.admin
  await page.goto('/admin/settings/contact-messages')
  await page.locator('input[name="retentionMonths"]').fill('18')
  await page.locator('.settings-section__footer button[type="submit"]').click()
  await expect(page.locator('.toast').first()).toContainText('حُفظت')
  await page.goto('/admin/privacy-requests')
  await page.locator('.contact-privacy input[type="email"]').fill(ENTITY.email)
  await page
    .locator('.contact-privacy textarea')
    .fill('طلب صاحب البيانات للتحقق من التصدير في بيئة التكامل.')
  await page.locator('.contact-privacy button[type="submit"]').click()
  await expect(page.locator('.contact-privacy__result')).toContainText(flow.messageReference)
  backendOnly('admin', /^\/api\/v1\/privacy\/contact-messages\/export$/)
})

test('the Content Officer publishes a new privacy version and the public page shows it', async () => {
  const { page } = sessions.content
  await page.goto('/admin/settings/homepage')
  await page.getByRole('tab', { name: 'سياسة الخصوصية' }).click()
  await expect(page.locator('[data-legal-kind="privacy"] tbody tr').first()).toBeVisible()
  await page.getByRole('button', { name: 'مسودة جديدة من النص الحالي' }).click()
  flow.legalVersion = `it-${RUN}`.slice(0, 20)
  await page.locator('input[name="version"]').fill(flow.legalVersion)
  await page.locator('input[name="title.ar"]').fill('سياسة الخصوصية — منصة مؤشر')
  await page.locator('input[name="title.en"]').fill('Privacy Policy — Moashir')
  await page
    .locator('textarea[name="body.ar"]')
    .fill('## 1. مقدمة\n\nنص سياسة الخصوصية لبيئة التكامل.\n\n- زوار المنصة؛\n- الجهات المسجلة.')
  await page
    .locator('textarea[name="body.en"]')
    .fill(
      '## 1. Introduction\n\nThe integration privacy text.\n\n- visitors of the platform;\n- registered entities.',
    )
  await expect(page.locator('.legal-preview[lang="ar"] li')).toHaveCount(2)
  await page.locator('form.legal-editor button[type="submit"]').click()
  await expect(page.locator('.toast').first()).toBeVisible()
  const publish = page.getByRole('button', { name: 'نشر الإصدار' })
  await expect(publish).toBeEnabled()
  await shot(page, 'legal-draft')
  await publish.click()
  await page.locator('.modal .btn--primary').click()
  await expect(page.locator('.legal-viewer')).toContainText('الإصدار الحالي')
  backendOnly('content', /^\/api\/v1\/admin\/content\/legal\//)

  const visitor = sessions.entity.page
  await visitor.goto('/privacy')
  await expect(visitor.locator('main')).toContainText(flow.legalVersion)
  await expect(visitor.locator('main')).toContainText('نص سياسة الخصوصية لبيئة التكامل.')
  await shot(visitor, 'privacy-published')
})

test('an accredited request is completed with its certificate, downloaded and verified', async () => {
  test.setTimeout(600000)
  const entity = sessions.entity.page
  const opened = execFileSync(
    'bash',
    ['scripts/integration/backend.sh', 'open-accredited', ENTITY.email],
    {
      encoding: 'utf8',
      env: process.env,
    },
  )
  flow.assessmentId = Number(opened.trim().split(/\s+/).pop())
  expect(flow.assessmentId).toBeGreaterThan(0)
  await completeForm(entity, flow.assessmentId)
  const submitted = await apiOk(entity, 'POST', `/assessments/${flow.assessmentId}/submit`, {
    body: {},
  })
  expect(['submitted', 'awaiting_review_assignment']).toContain(submitted.data.status)

  const follower = sessions.follower.page
  const assessors = await apiOk(
    follower,
    'GET',
    `/requests/assessors?assessment_id=${flow.assessmentId}`,
  )
  const assessor = assessors.data.find((item) => item.name) ?? assessors.data[0]
  await apiOk(follower, 'POST', `/requests/${flow.assessmentId}/assign`, {
    body: { assessor_id: assessor.id },
  })

  const reviewer = sessions.assessor.page
  await apiOk(reviewer, 'POST', `/assessments/${flow.assessmentId}/start-review`, { body: {} })
  const review = await apiOk(reviewer, 'GET', `/assessments/${flow.assessmentId}/review`)
  await apiOk(reviewer, 'PUT', `/assessments/${flow.assessmentId}/review/scores`, {
    body: { scores: scoreSheetOf(review.data.criteria, '8') },
  })
  await reviewer.goto(`/admin/requests/${flow.assessmentId}/review`)
  await expect(reviewer.locator('.review-header')).toBeVisible()
  await reviewer.locator('.review-actions .btn--primary').click()
  const dialog = reviewer.locator('.modal')
  await expect(dialog.locator('input[type="checkbox"]')).toBeChecked()
  await shot(reviewer, 'complete-dialog')
  await dialog.locator('.btn--primary').click()
  await expect(reviewer.locator('.review-header')).toContainText('مكتمل')

  const admin = sessions.admin.page
  const download = admin.getByRole('button', { name: 'تنزيل الشهادة PDF' })
  await expect
    .poll(
      async () => {
        await admin.goto(`/admin/requests/${flow.assessmentId}`)
        await admin.locator('[data-certificate="current"]').first().waitFor({ timeout: 10000 })
        return download.isVisible()
      },
      { timeout: 90000, intervals: [3000] },
    )
    .toBe(true)
  await shot(admin, 'request-certificate')
  const file = admin.waitForEvent('download')
  await download.click()
  expect((await file).suggestedFilename()).toMatch(/\.pdf$/i)
  const code = (await admin.locator('.request-certificate__facts a bdi').textContent()).trim()
  backendOnly('admin', /^\/api\/v1\/certificates$/)

  await entity.goto(`/verify/${code.replaceAll(' ', '')}`)
  await expect(entity.locator('.verification-result')).toBeVisible()
  await expect(entity.locator('main')).toContainText(ENTITY.nameAr)
  await shot(entity, 'verify-valid')
})

test('the audit log lists the certificate issue and exports it', async () => {
  const { page } = sessions.admin
  await page.goto('/admin/audit-log')
  await page.locator('input[name="audit-event"]').fill('certificate.issued')
  await page.locator('.audit-log__filters button[type="submit"]').click()
  await expect(page.locator(ROWS).first()).toContainText('certificate.issued')
  await page.locator(`${ROWS} button`).first().click()
  await expect(page.locator('.audit-entry')).toBeVisible()
  await page.locator('.modal').getByRole('button', { name: 'إغلاق' }).last().click()
  await shot(page, 'audit-log')
  const file = page.waitForEvent('download')
  await page.getByRole('button', { name: 'تصدير CSV' }).click()
  await page.locator('.modal button[type="submit"]').click()
  expect((await file).suggestedFilename()).toMatch(/^audit-log-.*\.csv$/)
  backendOnly('admin', /^\/api\/v1\/audit-logs/)
})

test('no unexpected API failure happened', () => {
  for (const [key, { api: watch }] of Object.entries(sessions)) {
    expect(watch.unexpected(ALLOWED), key).toEqual([])
  }
})
