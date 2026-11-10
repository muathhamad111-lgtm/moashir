import { randomUUID } from 'node:crypto'
import { writeFileSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { decisionAction } from './support/dialogs'
import { assertMailLogsConfigured, mailMark, waitForInvitationToken } from './support/mailbox'
import { STAFF, rememberPassword, signInStaff } from './support/accounts'
import { FOOTER_LEGAL_PROBES, GUEST_SESSION_PROBE, watchApi } from './support/apiWatch'
import { registerEntity } from './support/entities'
import { EVIDENCE_PDF, api, apiOk, scoreSheetOf } from './support/api'
import { chooseValue } from '../support/dropdown'

const RUN = Date.now().toString(36)
const DAY_MS = 24 * 60 * 60 * 1000
const ENTITY = {
  email: `office.${RUN}@ataa-association.example.sa`,
  password: `Ataa-${randomUUID()}`,
  nameAr: 'جمعية العطاء للتنمية الأهلية',
  nameEn: 'Al-Ataa Community Development Association',
  person: 'نورة بنت فهد العتيبي',
}
const COMMITTEE_ASSESSOR = {
  email: `committee.${RUN}@assessors.example.sa`,
  name: 'د. سلمان بن عادل الحربي',
  password: `Committee-${randomUUID()}`,
}
const CV_TEXT =
  'تعتمد الجمعية منهجية موثقة لتخطيط برامجها وقياس أثرها، وتراجع نتائجها كل ربع سنة مع مجلس الإدارة.'

const sessions = {}
const flow = {}

function shot(page, name) {
  return page.screenshot({ path: `test-results/integration-award-${name}.png`, fullPage: true })
}

async function openSession(browser, key) {
  const context = await browser.newContext({ locale: 'ar-SA' })
  const page = await context.newPage()
  sessions[key] = { context, page, api: watchApi(page) }
  return sessions[key]
}

function inDays(days) {
  return new Date(Date.now() + days * DAY_MS).toISOString()
}

function rows(page) {
  return page.locator('.table:not(.table--loading) tbody .table__row')
}

async function userIdOf(page, email) {
  const { data } = await apiOk(page, 'GET', `/users?search=${encodeURIComponent(email)}`)
  return data.find((user) => user.email === email).id
}

async function fillEmptyScores(page, value) {
  const mains = page.locator('.criteria-tree__open')
  const count = await mains.count()
  for (let index = 0; index < count; index += 1) {
    await mains.nth(index).click()
    const fields = page.locator('.review-main .raw-score input')
    const total = await fields.count()
    for (let field = 0; field < total; field += 1) {
      if (!(await fields.nth(field).inputValue())) await fields.nth(field).fill(value)
    }
  }
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
  throw new Error('The advanced form did not become complete.')
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async ({ browser, request }) => {
  assertMailLogsConfigured()
  const health = await request.get('/api/v1/health', { headers: { Accept: 'application/json' } })
  expect(health.status()).toBe(200)
  for (const key of ['admin', 'entity', 'assessor', 'filtering', 'secretary', 'committee'])
    await openSession(browser, key)
})

test.afterAll(async () => {
  const calls = Object.fromEntries(
    Object.entries(sessions).map(([key, { api: watch }]) => [key, watch.calls]),
  )
  writeFileSync('test-results/integration-award-api-calls.json', JSON.stringify(calls, null, 1))
  await Promise.all(Object.values(sessions).map(({ context }) => context.close()))
})

test('the staff of the award cycle sign in and a new entity registers', async () => {
  test.setTimeout(600000)
  await signInStaff(sessions.admin.page, STAFF.systemAdmin)
  await signInStaff(sessions.assessor.page, STAFF.assessor)
  await signInStaff(sessions.filtering.page, STAFF.filteringOfficer)
  await signInStaff(sessions.secretary.page, STAFF.awardSecretary)
  await registerEntity(sessions.entity.page, ENTITY)
  await expect(sessions.secretary.page.locator('.navbar--app')).toBeVisible()
})

test('the administrator prepares and opens an award cycle with its members and initial assessor', async () => {
  const { page } = sessions.admin
  const criteria = await apiOk(page, 'GET', '/assessments/criteria')
  const regions = await apiOk(page, 'GET', '/regions')
  flow.assessorId = await userIdOf(page, STAFF.assessor)
  const cycle = await apiOk(page, 'POST', '/award/cycles', {
    body: {
      name: { ar: `دورة جائزة مؤشر التكاملية ${RUN}`, en: `Moashir integration award ${RUN}` },
      submission_start: inDays(1),
      submission_end: inDays(30),
      committee_start: inDays(40),
      committee_end: inDays(60),
      region_ids: regions.data.map((region) => region.id),
      criteria_version_id: criteria.data.version_id,
    },
  })
  flow.cycleId = cycle.data.id
  await apiOk(page, 'PUT', `/award/cycles/${flow.cycleId}/members`, {
    body: {
      filtering_officer_ids: [await userIdOf(page, STAFF.filteringOfficer)],
      award_secretary_ids: [await userIdOf(page, STAFF.awardSecretary)],
    },
  })
  await apiOk(page, 'PUT', `/award/cycles/${flow.cycleId}/initial-assessors`, {
    body: { assessor_ids: [flow.assessorId] },
  })
  const opened = await apiOk(page, 'POST', `/award/cycles/${flow.cycleId}/open`, { body: {} })
  expect(opened.data.status).toBe('submission_open')
})

test('the entity applies, completes and submits the advanced request', async () => {
  test.setTimeout(400000)
  const { page } = sessions.entity
  const applied = await apiOk(page, 'POST', '/assessments/advanced', {
    body: { award_cycle_id: flow.cycleId },
  })
  flow.assessmentId = applied.data.id
  await completeForm(page, flow.assessmentId)
  const submitted = await apiOk(page, 'POST', `/assessments/${flow.assessmentId}/submit`, {
    body: {},
  })
  expect(submitted.data.status).toBe('awaiting_review_assignment')
})

test('the request is assessed, the submissions close and the filtering officer qualifies it', async () => {
  await apiOk(sessions.admin.page, 'POST', `/requests/${flow.assessmentId}/assign`, {
    body: { assessor_id: flow.assessorId },
  })
  const assessor = sessions.assessor.page
  await apiOk(assessor, 'POST', `/assessments/${flow.assessmentId}/start-review`, { body: {} })
  const review = await apiOk(assessor, 'GET', `/assessments/${flow.assessmentId}/review`)
  await apiOk(assessor, 'PUT', `/assessments/${flow.assessmentId}/review/scores`, {
    body: { scores: scoreSheetOf(review.data.criteria, '8') },
  })
  await apiOk(assessor, 'POST', `/assessments/${flow.assessmentId}/complete`, { body: {} })
  await apiOk(sessions.admin.page, 'POST', `/award/cycles/${flow.cycleId}/close-submissions`, {
    body: {},
  })
  const qualified = await apiOk(
    sessions.filtering.page,
    'POST',
    `/award/cycles/${flow.cycleId}/filtering/${flow.assessmentId}/qualify`,
    { body: {} },
  )
  expect(qualified.data.status).toBe('qualified_for_committee')
})

test('the administrator invites a committee assessor from the users screen and the invitation is accepted', async ({
  browser,
}) => {
  test.setTimeout(600000)
  const { page, api: watch } = sessions.admin
  await page.goto('/admin/settings/users')
  await expect(page.locator('.demo-notice')).toHaveCount(0)
  await page.locator('.toolbar__actions .btn--primary').click()
  const dialog = page.locator('.modal__panel')
  await dialog.locator('input[name="name"]').fill(COMMITTEE_ASSESSOR.name)
  await dialog.locator('input[name="email"]').fill(COMMITTEE_ASSESSOR.email)
  await dialog.locator('.user-form__roles label', { hasText: 'المقيّم' }).first().click()
  const mark = mailMark(COMMITTEE_ASSESSOR.email)
  await dialog.locator('button[type="submit"]').click()
  await expect(dialog).toBeHidden()
  await page.locator('.toolbar__search input').fill(COMMITTEE_ASSESSOR.email)
  await expect(rows(page)).toHaveCount(1)
  await expect(rows(page).first()).toContainText('مدعو')
  await shot(page, 'users-invited')
  const token = await waitForInvitationToken(COMMITTEE_ASSESSOR.email, mark)
  const guest = await browser.newContext({ locale: 'ar-SA' })
  const guestPage = await guest.newPage()
  await guestPage.goto('/auth/login')
  const terms = await api(guestPage, 'GET', '/terms/current')
  const accepted = await api(guestPage, 'POST', `/auth/invitations/${token}/accept`, {
    body: {
      password: COMMITTEE_ASSESSOR.password,
      password_confirmation: COMMITTEE_ASSESSOR.password,
      ...(terms.body?.data?.terms?.id ? { terms_version_id: terms.body.data.terms.id } : {}),
    },
  })
  expect(accepted.status).toBeLessThan(300)
  await guest.close()
  rememberPassword(COMMITTEE_ASSESSOR.email, COMMITTEE_ASSESSOR.password)
  await signInStaff(sessions.committee.page, COMMITTEE_ASSESSOR.email)
  expect(watch.unexpected([GUEST_SESSION_PROBE, ...FOOTER_LEGAL_PROBES])).toEqual([])
})

test('the administrator appoints the committee assessor and starts the committee assessment', async () => {
  const { page, api: watch } = sessions.admin
  await page.goto(`/admin/award/cycles/${flow.cycleId}/committee`)
  await page.locator('.award-page-header__actions .btn--primary').click()
  const dialog = page.locator('.modal__panel')
  await dialog.locator('.dropdown-combobox__input').click()
  await page.getByRole('option', { name: COMMITTEE_ASSESSOR.name }).click()
  await dialog.locator('button[type="submit"]').click()
  await expect(dialog).toBeHidden()
  await expect(rows(page)).toHaveCount(1)
  await shot(page, 'committee-roster')
  await page.goto(`/admin/award/cycles/${flow.cycleId}`)
  await page.locator('.cycle-actions button', { hasText: 'بدء تقييم اللجنة' }).click()
  await decisionAction(page).click()
  await expect(page.locator('.cycle-editor__title-row')).toContainText('تقييم اللجنة')
  expect(watch.unexpected([GUEST_SESSION_PROBE, ...FOOTER_LEGAL_PROBES])).toEqual([])
})

test('the committee assessor scores the entity blindly and submits the evaluation', async () => {
  test.setTimeout(240000)
  const { page, api: watch } = sessions.committee
  await page.goto('/admin/committee/assignments')
  await expect(rows(page)).toHaveCount(1)
  await rows(page).first().locator('.cycle-table__link').click()
  await expect(page.locator('.committee-entity, .review-entity').first()).toBeVisible()
  await fillEmptyScores(page, '9')
  await page.locator('.committee-actions .btn--secondary').click()
  await expect(page.locator('.review-actions__status')).toContainText('كل شيء محفوظ')
  await page.locator('.committee-actions .btn--primary').click()
  await decisionAction(page).click()
  await expect(page.locator('.committee-workspace')).toContainText('أُرسل وأُقفل')
  await shot(page, 'committee-evaluation')
  expect(watch.unexpected([GUEST_SESSION_PROBE, ...FOOTER_LEGAL_PROBES])).toEqual([])
})

test('the Award Secretary approves the average, ranks, approves the results and announces them', async () => {
  test.setTimeout(240000)
  const { page, api: watch } = sessions.secretary
  await page.goto(`/admin/award/cycles/${flow.cycleId}/secretary/${flow.assessmentId}`)
  await expect(page.locator('.secretary-facts__percent')).toBeVisible()
  await shot(page, 'secretary-request')
  await page.locator('.filtering-actions button', { hasText: 'اعتماد المتوسط' }).click()
  await page.locator('.modal__panel button[type="submit"]').click()
  await expect(page.locator('.secretary-facts')).toContainText('معتمد')
  await page.goto(`/admin/award/cycles/${flow.cycleId}/results`)
  const region = page.locator('.region-results').first()
  await chooseValue(region.locator('button[role="combobox"]'), '1')
  await expect(region.locator('.region-results__actions .btn--primary')).toBeEnabled()
  await region.locator('.region-results__actions .btn--primary').click()
  await decisionAction(page).click()
  await expect(region).toContainText('معتمدة')
  await page.locator('.results-tabs__link').nth(1).click()
  await chooseValue(rows(page).first().locator('button[role="combobox"]'), '1')
  await expect(page.locator('.region-results__actions .btn--primary')).toBeEnabled()
  await page.locator('.region-results__actions .btn--primary').click()
  await decisionAction(page).click()
  await expect(page.locator('.announcement-card .btn--primary')).toBeEnabled()
  await page.locator('.announcement-card .btn--primary').click()
  await decisionAction(page).click()
  await expect(page.locator('.announcement-card')).toContainText('أُعلنت النتائج بتاريخ')
  await shot(page, 'results-announced')
  expect(watch.unexpected([GUEST_SESSION_PROBE, ...FOOTER_LEGAL_PROBES])).toEqual([])
})

test('the entity reads its announced result without the committee average', async () => {
  const { page, api: watch } = sessions.entity
  await page.goto(`/assessment/advanced/result/${flow.assessmentId}`)
  await expect(page.locator('.award-outcome__title')).toHaveText('فائز بالجائزة على مستوى المملكة')
  await expect(page.locator('.award-outcome')).toContainText('الأول')
  await shot(page, 'entity-result')
  expect(watch.unexpected([GUEST_SESSION_PROBE, ...FOOTER_LEGAL_PROBES])).toEqual([])
})
