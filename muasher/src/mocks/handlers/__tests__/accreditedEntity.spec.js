import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { findEntity } from '@/mocks/stores/entitiesStore'
import { findAssessment, resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { resetAssessmentSettings } from '@/mocks/stores/accredited/assessmentSettingsStore'
import { resetAccreditedOpening } from '@/mocks/stores/accredited/accreditedOpening'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { announcePaymentPaid } from '@/mocks/stores/finance/paymentHooks'
import { notificationsStore } from '@/mocks/stores/notificationsStore'
import { discardQueuedJobs, runQueuedJobs } from '@/mocks/stores/mockQueue'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'
import { toCamelKey, toWire } from '@/api/caseMapping'
import { MockRejection } from '@/mocks/contract/rejection'
import { currentSessionUser } from '@/mocks/stores/sessionStore'
import { uploadEvidence } from '@/mocks/stores/accredited/evidenceFiles'
import { evidenceItem } from '@/mocks/stores/accredited/accreditedSerializers'
import { boundSubsOf } from '@/mocks/stores/accredited/accreditedRecords'

const server = setupServer(...handlers)

const NAMAA = { id: 6001, roles: ['entity'], entityId: 6001 }
const ATAA = { id: 6004, roles: ['entity'], entityId: 6004 }
const ALBIRR = { id: 5001, roles: ['entity'], entityId: 5001 }
const FOLLOWER = { id: 8, roles: ['request_follower', 'filtering_officer'] }
// The fixtures carry fixed dates, so the clock is pinned to keep these flows deterministic.
const FIXTURE_NOW = new Date('2026-11-05T09:00:00Z')
const PDF = '%PDF-1.4\n% evidence\n%%EOF\n'
const EICAR = '%PDF-1.4\nEICAR-STANDARD-ANTIVIRUS-TEST-FILE\n%%EOF\n'

const ASSESSMENT_KEYS = [
  'award_cycle',
  'award_cycle_id',
  'certificate',
  'completed_at',
  'created_at',
  'criteria_update_in_progress',
  'criteria_version_id',
  'entity',
  'id',
  'is_frozen',
  'is_open',
  'kit',
  'level',
  'number',
  'path',
  'path_label',
  'payment',
  'period',
  'period_label',
  'period_state',
  'result',
  'row_key',
  'row_type',
  'scale_kind',
  'status',
  'submitted_at',
  'updated_at',
]
const FORM_KEYS = [
  'assessment',
  'completion_request',
  'criteria',
  'criteria_update',
  'cv',
  'evidence',
  'missing',
  'upload_limits',
]
const EVIDENCE_KEYS = [
  'attachment_key',
  'code',
  'file_name',
  'id',
  'mime_type',
  'scan_status',
  'size_bytes',
  'sub_criterion_id',
  'uploaded_at',
]
const COMPLETION_KEYS = [
  'deadline_at',
  'expiry_outcome',
  'id',
  'items',
  'notes',
  'requested_at',
  'resolved_at',
  'status',
]

async function call(method, path, body, locale = 'en') {
  const init = { method, headers: { 'accept-language': locale } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  const text = await response.text()
  const json =
    /application\/json/.test(response.headers.get('content-type') ?? '') && text
      ? JSON.parse(text)
      : null
  return { status: response.status, body: json }
}

async function upload(assessmentId, fields, content = PDF, name = 'evidence-file.pdf') {
  const form = new FormData()
  form.append('file', new File([content], name, { type: 'application/pdf' }))
  Object.entries(fields).forEach(([key, value]) => form.append(toCamelKey(key), String(value)))
  const record = findAssessment(assessmentId)
  try {
    const row = await uploadEvidence(currentSessionUser(), record, form)
    return { status: 201, body: { data: toWire(evidenceItem(row, boundSubsOf(record))) } }
  } catch (error) {
    if (!(error instanceof MockRejection)) throw error
    return { status: error.status, body: { code: error.code, meta: toWire(error.meta) } }
  }
}

const keys = (value) => Object.keys(value).sort()

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  vi.useFakeTimers({ now: FIXTURE_NOW, toFake: ['Date'] })
  resetCriteriaStore()
  resetEntitiesMock()
  resetFinanceState()
  resetAssessmentSettings()
  resetAccreditedOpening()
  resetAssessmentsStore()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
  discardQueuedJobs()
  vi.useRealTimers()
})
afterAll(() => server.close())

describe('accredited mock — entity reads', () => {
  it('answers the current request, the latest closed one and refuses staff', async () => {
    seedMockSession(NAMAA)
    const { status, body } = await call('GET', '/assessments/accredited/current')
    expect(status).toBe(200)
    expect(keys(body.data)).toEqual([
      'active',
      'apply',
      'awaiting_payment',
      'latest_closed',
      'periodic',
      'pricing',
      'submission',
    ])
    expect(body.data.awaiting_payment).toBeNull()
    expect(keys(body.data.active)).toEqual([...ASSESSMENT_KEYS, 'progress'].sort())
    expect(keys(body.data.active.progress)).toEqual(['remaining', 'subs_complete', 'subs_total'])
    expect(body.data.active.progress.subs_complete).toBe(
      body.data.active.progress.subs_total - body.data.active.progress.remaining.length,
    )
    expect(body.data.active).toMatchObject({
      id: 9101,
      number: 'ACC-2026-000004',
      path: 'accredited',
      path_label: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
      status: 'draft',
      is_open: true,
      result: null,
    })
    expect(body.data.latest_closed).toMatchObject({ id: 9104, status: 'completed', is_open: false })
    expect(keys(body.data.latest_closed.result)).toEqual(['max_total', 'percent', 'total'])
    seedMockSession(FOLLOWER)
    expect((await call('GET', '/assessments/accredited/current')).status).toBe(403)
  })

  it('returns the form with the bound criteria, CV, evidence, missing items and limits, never scores', async () => {
    seedMockSession(NAMAA)
    const { body } = await call('GET', '/assessments/9101/form')
    expect(keys(body.data)).toEqual(FORM_KEYS)
    expect(body.data).not.toHaveProperty('scores')
    expect(body.data.upload_limits).toEqual({
      max_file_mb: 20,
      allowed_types: ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'png'],
      max_files_per_sub: 10,
    })
    expect(keys(body.data.evidence[0])).toEqual(EVIDENCE_KEYS)
    expect(body.data.evidence.map((item) => item.scan_status)).toEqual(
      expect.arrayContaining(['clean', 'pending', 'infected']),
    )
    expect(body.data.missing).toEqual({
      missing_cv: [{ sub_criterion_id: 802, code: 'M8.2' }],
      missing_attachments: [
        { sub_criterion_id: 402, code: 'M4.2', attachment_key: 'sub_402_1' },
        { sub_criterion_id: 802, code: 'M8.2', attachment_key: 'sub_802_1' },
      ],
    })
    expect(body.data.cv['101']).toContain('الرؤية')
    seedMockSession(ALBIRR)
    expect((await call('GET', '/assessments/9101/form')).status).toBe(404)
  })

  it('shows staff readers clean evidence only and the owner every present file', async () => {
    seedMockSession(FOLLOWER)
    const staff = await call('GET', '/assessments/9101/evidence')
    expect(staff.body.data.every((item) => item.scan_status === 'clean')).toBe(true)
    seedMockSession(NAMAA)
    const own = await call('GET', '/assessments/9101/evidence?filter[sub_criterion_id]=402')
    expect(own.body.data.map((item) => item.scan_status)).toEqual(['infected'])
    expect((await call('GET', '/assessments/9101/evidence?filter[kind]=x')).status).toBe(422)
  })
})

describe('accredited mock — entity writes', () => {
  it('saves CV entries partially and refuses unknown, CV-less and other-entity sub-criteria', async () => {
    seedMockSession(NAMAA)
    const saved = await call('PUT', '/assessments/9101/cv', { cv: { 802: 'نص السيرة', 101: null } })
    expect(saved.status).toBe(200)
    expect(keys(saved.body.data)).toEqual(['assessment', 'cv'])
    expect(saved.body.data.cv['802']).toBe('نص السيرة')
    expect(saved.body.data.cv).not.toHaveProperty('101')
    expect((await call('PUT', '/assessments/9101/cv', { cv: { 99999: 'x' } })).body.code).toBe(
      'assessment.sub_not_in_version',
    )
    const cvLess = await call('PUT', '/assessments/9101/cv', { cv: { 702: 'x' } })
    expect([cvLess.status, cvLess.body.code, Object.keys(cvLess.body.errors)]).toEqual([
      422,
      'cv.not_allowed',
      ['cv.702'],
    ])
    expect((await call('PUT', '/assessments/9101/cv', { cv: {} })).status).toBe(422)
    seedMockSession(FOLLOWER)
    expect((await call('PUT', '/assessments/9101/cv', { cv: { 802: 'x' } })).status).toBe(403)
    seedMockSession(ALBIRR)
    expect((await call('PUT', '/assessments/9101/cv', { cv: { 802: 'x' } })).status).toBe(404)
  })

  it('refuses submission with the missing items, then submits with a due date', async () => {
    seedMockSession(NAMAA)
    const incomplete = await call('POST', '/assessments/9101/submit', {})
    expect([incomplete.status, incomplete.body.code]).toEqual([422, 'submission.incomplete'])
    expect(Object.keys(incomplete.body.errors)).toEqual([
      'cv.802',
      'attachments.402.sub_402_1',
      'attachments.802.sub_802_1',
    ])
    expect(incomplete.body.meta.missing_attachments).toHaveLength(2)
    await call('PUT', '/assessments/9101/cv', { cv: { 802: 'نص السيرة' } })
    expect(
      (await upload(9101, { sub_criterion_id: 402, attachment_key: 'sub_402_1' })).status,
    ).toBe(201)
    expect(
      (await upload(9101, { sub_criterion_id: 802, attachment_key: 'sub_802_1' })).status,
    ).toBe(201)
    const submitted = await call('POST', '/assessments/9101/submit', {})
    expect(submitted.body.data).toMatchObject({ status: 'submitted', is_open: true })
    expect(findAssessment(9101).dueAt).not.toBeNull()
    const again = await call('POST', '/assessments/9101/submit', {})
    expect([again.status, again.body.code]).toEqual([409, 'state.transition_illegal'])
  })

  it('uploads evidence as pending, scans it in the background and refuses wrong content, keys and full slots', async () => {
    seedMockSession(NAMAA)
    const created = await upload(9101, { sub_criterion_id: 802, attachment_key: 'sub_802_1' })
    expect(created.status).toBe(201)
    expect(keys(created.body.data)).toEqual(EVIDENCE_KEYS)
    expect(created.body.data).toMatchObject({
      sub_criterion_id: 802,
      code: 'M8.2',
      scan_status: 'pending',
      mime_type: 'application/pdf',
    })
    runQueuedJobs()
    const listed = await call('GET', '/assessments/9101/evidence?filter[sub_criterion_id]=802')
    expect(listed.body.data[0].scan_status).toBe('clean')
    expect(
      (await upload(9101, { sub_criterion_id: 802 }, 'plain text', 'notes.pdf')).body.code,
    ).toBe('file.content_mismatch')
    expect(
      (await upload(9101, { sub_criterion_id: 802, attachment_key: 'sub_101_1' })).body.code,
    ).toBe('attachment.key_invalid')
    for (let index = 0; index < 9; index += 1) await upload(9101, { sub_criterion_id: 802 })
    const full = await upload(9101, { sub_criterion_id: 802 })
    expect([full.status, full.body.code, full.body.meta]).toEqual([
      422,
      'attachment.sub_full',
      { max_files_per_sub: 10 },
    ])
  })

  it('marks an infected upload as refused, tells the entity with EVD-02 and keeps it out of the slot', async () => {
    seedMockSession(NAMAA)
    const before = notificationsStore.length
    await upload(9101, { sub_criterion_id: 802, attachment_key: 'sub_802_1' }, EICAR)
    runQueuedJobs()
    const listed = await call('GET', '/assessments/9101/evidence?filter[sub_criterion_id]=802')
    expect(listed.body.data[0].scan_status).toBe('infected')
    expect(notificationsStore.slice(before).map((item) => [item.code, item.recipientRole])).toEqual(
      [['EVD-02', 'entity']],
    )
    expect(notificationsStore.at(-1).body.en).not.toContain('evidence-file.pdf')
    const form = await call('GET', '/assessments/9101/form')
    expect(form.body.data.missing.missing_attachments.map((item) => item.attachment_key)).toContain(
      'sub_802_1',
    )
  })

  it('removes evidence, issues signed links for clean files only and answers 409 before the scan', async () => {
    seedMockSession(NAMAA)
    const pending = await upload(9101, { sub_criterion_id: 802 })
    const notClean = await call(
      'POST',
      `/assessments/9101/evidence/${pending.body.data.id}/download`,
    )
    expect([notClean.status, notClean.body.code]).toEqual([409, 'file.not_clean'])
    const clean = (await call('GET', '/assessments/9101/evidence?filter[sub_criterion_id]=101'))
      .body.data[0]
    const link = await call('POST', `/assessments/9101/evidence/${clean.id}/download`)
    expect(keys(link.body.data)).toEqual(['expires_at', 'file_name', 'url'])
    expect(link.body.data.url).toMatch(/\/api\/v1\/files\/[0-9a-f-]{36}\/download\?expires=/)
    expect(
      (await call('DELETE', `/assessments/9101/evidence/${pending.body.data.id}`)).status,
    ).toBe(204)
    expect(
      (await call('DELETE', `/assessments/9101/evidence/${pending.body.data.id}`)).status,
    ).toBe(404)
  })

  it('limits a resubmission to the requested items and returns the request to the assessor', async () => {
    seedMockSession(ATAA)
    const form = await call('GET', '/assessments/9106/form')
    expect(keys(form.body.data.completion_request)).toEqual(COMPLETION_KEYS)
    expect(form.body.data.completion_request).toMatchObject({
      status: 'open',
      expiry_outcome: 'continue_as_submitted',
    })
    expect(form.body.data.completion_request.items).toEqual([
      { sub_criterion_id: 202, code: 'M2.2', kind: 'cv', note: expect.any(String) },
      { sub_criterion_id: 402, code: 'M4.2', kind: 'evidence', note: expect.any(String) },
    ])
    expect((await call('PUT', '/assessments/9106/cv', { cv: { 101: 'x' } })).body.code).toBe(
      'completion.item_not_requested',
    )
    expect((await upload(9106, { sub_criterion_id: 101 })).body.code).toBe(
      'completion.item_not_requested',
    )
    expect((await call('PUT', '/assessments/9106/cv', { cv: { 202: 'سيرة محدثة' } })).status).toBe(
      200,
    )
    expect(
      (await upload(9106, { sub_criterion_id: 402, attachment_key: 'sub_402_1' })).status,
    ).toBe(201)
    const resubmitted = await call('POST', '/assessments/9106/submit', {})
    expect(resubmitted.body.data.status).toBe('under_review')
    expect(findAssessment(9106).completionRequests[0].status).toBe('resubmitted')
  })

  it('freezes the requests of a deleted entity and asks the Super Admin for a reason', async () => {
    findEntity(6001).deletedAt = '2026-10-30T08:00:00Z'
    seedMockSession(NAMAA)
    const frozen = await call('PUT', '/assessments/9101/cv', { cv: { 802: 'x' } })
    expect([frozen.status, frozen.body.code]).toEqual([409, 'entity.deleted'])
    findEntity(6001).deletedAt = null
    seedMockSession({ id: 900, roles: [], superAdmin: true })
    expect((await call('PUT', '/assessments/9101/cv', { cv: { 802: 'x' } })).body.code).toBe(
      'reason_required',
    )
  })
})

describe('accredited mock — results, opening, prices', () => {
  it('publishes the result with feedback per sub-criterion and never an assessor identity', async () => {
    seedMockSession(ALBIRR)
    const { body } = await call('GET', '/assessments/9102/result')
    expect(body.data).toMatchObject({ available: true, status: 'completed' })
    expect(keys(body.data.feedback[0])).toEqual([
      'code',
      'improvement_points',
      'name',
      'notes',
      'percent',
      'strengths',
      'sub_id',
    ])
    expect(JSON.stringify(body.data)).not.toMatch(/assessor|سلطان/)
    seedMockSession(NAMAA)
    expect((await call('GET', '/assessments/9101/result')).body.data).toEqual({
      id: 9101,
      number: 'ACC-2026-000004',
      path: 'accredited',
      path_label: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
      status: 'draft',
      is_frozen: false,
      available: false,
    })
  })

  it('opens a draft for a paid accredited payment once and refuses a second order while it is open', async () => {
    seedMockSession(ALBIRR)
    const key = crypto.randomUUID()
    const order = await wireFetch(`${MOCK_API_BASE}/payments`, {
      method: 'POST',
      headers: { 'content-type': 'application/json', 'idempotency-key': key },
      body: JSON.stringify({ purpose: 'accredited' }),
    })
    const payment = (await order.json()).data
    const stored = (await import('@/mocks/stores/finance/financeState')).findPayment(payment.id)
    stored.status = 'paid'
    announcePaymentPaid(stored)
    announcePaymentPaid(stored)
    runQueuedJobs()
    const current = await call('GET', '/assessments/accredited/current')
    expect(current.body.data.active).toMatchObject({ number: 'ACC-2026-000009', status: 'draft' })
    const blocked = await wireFetch(`${MOCK_API_BASE}/payments`, {
      method: 'POST',
      headers: { 'content-type': 'application/json', 'idempotency-key': crypto.randomUUID() },
      body: JSON.stringify({ purpose: 'accredited' }),
    })
    const refusal = await blocked.json()
    expect([blocked.status, refusal.code, refusal.meta]).toEqual([
      409,
      'accredited.request_open',
      { assessment_id: current.body.data.active.id },
    ])
  })

  it('serves the public prices before VAT, VAT and total to guests', async () => {
    const { status, body } = await call('GET', '/content/prices')
    expect(status).toBe(200)
    expect(body.data.accredited).toEqual({
      price_before_vat: '1000.00',
      vat_rate: '15.00',
      vat_amount: '150.00',
      total: '1150.00',
      currency: 'SAR',
    })
    expect(keys(body.data)).toEqual(['accredited', 'bag', 'paths'])
    expect(body.data.paths.self).toEqual({ price: null, is_free: true, availability: 'available' })
    expect(body.data.paths.accredited).toMatchObject({ is_free: false, availability: 'available' })
  })
})
