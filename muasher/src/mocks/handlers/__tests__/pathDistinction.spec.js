import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { resetAssessmentSettings } from '@/mocks/stores/accredited/assessmentSettingsStore'
import { resetPathSettings } from '@/mocks/stores/accredited/pathSettingsStore'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { resetStaffUsers } from '@/mocks/stores/staffUsers'
import { resetCertificateSettings } from '@/mocks/stores/certificateSettingsStore'
import { discardQueuedJobs } from '@/mocks/stores/mockQueue'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...handlers)
const FOLLOWER = { id: 8, roles: ['request_follower', 'filtering_officer'] }
const SCOPED_FOLLOWER = { id: 28, roles: ['request_follower'] }
const SYSTEM_ADMIN = { id: 1, roles: ['system_admin'] }
const SUPER_ADMIN = { id: 900, roles: [], superAdmin: true }
const SULTAN = { id: 10, roles: ['assessor'] }
const REASON = 'تحديث مسارات الحساب بعد مراجعة توزيع الأعمال.'

async function call(method, path, body) {
  const init = { method, headers: { 'accept-language': 'en' } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null }
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetCriteriaStore()
  resetEntitiesMock()
  resetFinanceState()
  resetAssessmentSettings()
  resetPathSettings()
  resetAssessmentsStore()
  resetStaffUsers()
  resetCertificateSettings()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
  discardQueuedJobs()
})
afterAll(() => server.close())

describe('per-path queues', () => {
  it('serves one path per call with the counts of every followed path', async () => {
    seedMockSession(FOLLOWER)
    const { body } = await call('GET', '/requests')
    expect(body.meta.path).toBe('accredited')
    expect(body.meta.path_counts.map((entry) => entry.path)).toEqual(['accredited', 'advanced'])
    expect(body.meta.path_counts[0]).toMatchObject({
      path_label: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
      awaiting_assignment: 1,
    })
    expect(body.data.every((row) => row.path === 'accredited')).toBe(true)
    const advanced = await call('GET', '/requests?filter[path]=advanced')
    expect(advanced.body.meta.path).toBe('advanced')
    expect(advanced.body.data.every((row) => row.path === 'advanced')).toBe(true)
  })

  it('limits a scoped follower to the followed path and hides the other path records', async () => {
    seedMockSession(SCOPED_FOLLOWER)
    const { body } = await call('GET', '/requests')
    expect(body.meta.path_counts.map((entry) => entry.path)).toEqual(['accredited'])
    expect((await call('GET', '/requests?filter[path]=advanced')).status).toBe(422)
    expect((await call('GET', '/requests?filter[cycle_id]=31')).status).toBe(422)
    expect((await call('GET', '/requests/9203')).status).toBe(404)
    expect((await call('GET', '/requests/9105')).status).toBe(200)
    expect((await call('GET', '/requests/assessors?assessment_id=9203')).status).toBe(422)
  })

  it('returns the assigned list of one path with the counts per tab', async () => {
    seedMockSession(SULTAN)
    const { body } = await call('GET', '/requests/assigned')
    expect(body.meta.path).toBe('accredited')
    expect(body.meta.path_counts.map((entry) => entry.path)).toEqual(['accredited', 'advanced'])
    expect(Object.keys(body.meta.path_counts[0]).sort()).toEqual([
      'completed',
      'in_progress',
      'new',
      'path',
      'path_label',
    ])
    expect((await call('GET', '/requests/assigned?filter[path]=self')).status).toBe(422)
  })
})

describe('qualified assessors', () => {
  it('lists only the assessors qualified for the path of the request', async () => {
    seedMockSession(FOLLOWER)
    const accredited = (await call('GET', '/requests/assessors?assessment_id=9105')).body.data
    const ids = accredited.map((person) => person.id)
    expect(ids).not.toContain(27)
    expect(ids).not.toContain(30)
    expect(ids).toContain(31)
  })

  it('refuses an assessor who is not qualified for the path with the required qualification', async () => {
    seedMockSession(FOLLOWER)
    const refused = await call('POST', '/requests/9105/assign', { assessor_id: 30 })
    expect(refused.status).toBe(422)
    expect(refused.body.code).toBe('assessor.not_qualified')
    expect(refused.body.meta).toEqual({ required_qualification: 'accredited', path: 'accredited' })
    expect(refused.body.errors.assessor_id).toHaveLength(1)
  })
})

describe('user path access', () => {
  it('sets the qualifications of an assessor and refuses other accounts, the own account and a missing Super Admin reason', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const updated = await call('PUT', '/users/27/assessor-qualifications', {
      qualifications: ['accredited'],
    })
    expect(updated.body.data.assessor_qualifications).toEqual(['accredited'])
    const notAssessor = await call('PUT', '/users/22/assessor-qualifications', {
      qualifications: ['accredited'],
    })
    expect(notAssessor.body.code).toBe('assessor_qualification.not_assessor')
    expect(notAssessor.body.errors.qualifications).toHaveLength(1)
    expect(
      (await call('PUT', '/users/1/assessor-qualifications', { qualifications: [] })).body.code,
    ).toBe('sod_self_grant')
    seedMockSession(SUPER_ADMIN)
    expect(
      (await call('PUT', '/users/27/assessor-qualifications', { qualifications: [] })).body.code,
    ).toBe('reason_required')
    expect(
      (
        await call('PUT', '/users/27/assessor-qualifications', {
          qualifications: [],
          reason: REASON,
        })
      ).body.data.assessor_qualifications,
    ).toEqual([])
  })

  it('limits the request scope of a follower and returns it in the session', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const limited = await call('PUT', '/users/9/request-scope', {
      all_paths: false,
      paths: ['advanced'],
    })
    expect(limited.body.data.request_scope).toEqual({ all_paths: false, paths: ['advanced'] })
    expect(
      (await call('PUT', '/users/22/request-scope', { all_paths: false, paths: ['accredited'] }))
        .body.code,
    ).toBe('request_scope.not_follower')
    expect(
      (await call('PUT', '/users/9/request-scope', { all_paths: false, paths: [] })).status,
    ).toBe(422)
    seedMockSession({ id: 9, roles: ['request_follower'] })
    const session = (await call('GET', '/auth/session')).body.data
    expect(session.request_scope).toEqual({ all_paths: false, paths: ['advanced'] })
    expect(session.assessor_qualifications).toEqual([])
  })
})

describe('per-path settings', () => {
  it('reads the settings of each path with the price of the paid path only', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const accredited = (await call('GET', '/settings/assessment/paths/accredited')).body.data
    expect(Object.keys(accredited).sort()).toEqual([
      'entity_texts',
      'evidence_type_catalog',
      'is_paid',
      'path',
      'path_label',
      'price',
      'review_sla_days',
      'review_sla_limits',
      'upload_limit_bounds',
      'upload_limits',
    ])
    expect(accredited.price.price_before_vat).toBe('1000.00')
    const advanced = (await call('GET', '/settings/assessment/paths/advanced')).body.data
    expect(advanced).toMatchObject({ is_paid: false, price: null })
    expect((await call('GET', '/settings/assessment/paths/self')).status).toBe(404)
    seedMockSession(SULTAN)
    expect((await call('GET', '/settings/assessment/paths/accredited')).status).toBe(403)
  })

  it('updates limits, price and texts, and serves the texts to the entity guide', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const updated = await call('PUT', '/settings/assessment/paths/accredited', {
      review_sla_days: 21,
      evidence_max_files_per_sub: 6,
      price: '1250.00',
      entity_texts: { intro: { ar: 'تعريف محدث بالمسار.', en: 'An updated path introduction.' } },
    })
    expect(updated.body.data).toMatchObject({
      review_sla_days: 21,
      upload_limits: { max_files_per_sub: 6 },
      price: { price_before_vat: '1250.00' },
      entity_texts: { intro: { ar: 'تعريف محدث بالمسار.', en: 'An updated path introduction.' } },
    })
    const guide = (await call('GET', '/content/assessment-paths/accredited')).body.data
    expect(guide.entity_texts.intro.en).toBe('An updated path introduction.')
    const half = await call('PUT', '/settings/assessment/paths/accredited', {
      entity_texts: { after_submission: { ar: 'نص عربي فقط', en: '' } },
    })
    expect(half.status).toBe(422)
    expect(half.body.errors['entity_texts.after_submission.en']).toHaveLength(1)
    expect(
      (await call('PUT', '/settings/assessment/paths/advanced', { price: '100.00' })).status,
    ).toBe(422)
    seedMockSession(SUPER_ADMIN)
    expect(
      (await call('PUT', '/settings/assessment/paths/advanced', { review_sla_days: 10 })).body.code,
    ).toBe('reason_required')
  })
})

describe('certificate settings', () => {
  it('reads and updates the issuance switch and the ascending thresholds', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const { body } = await call('GET', '/settings/certificates')
    expect(body.data).toEqual({
      accredited_issuance_enabled: true,
      level_thresholds: { bronze: '60.00', silver: '70.00', gold: '80.00', diamond: '90.00' },
    })
    const descending = await call('PUT', '/settings/certificates', {
      level_thresholds: { bronze: '60', silver: '55', gold: '80', diamond: '90' },
    })
    expect(descending.status).toBe(422)
    const saved = await call('PUT', '/settings/certificates', {
      accredited_issuance_enabled: false,
      level_thresholds: { bronze: '65.5', silver: '72', gold: '84', diamond: '95' },
    })
    expect(saved.body.data).toEqual({
      accredited_issuance_enabled: false,
      level_thresholds: { bronze: '65.50', silver: '72.00', gold: '84.00', diamond: '95.00' },
    })
    const levels = (await call('GET', '/content/certificate-levels')).body.data
    expect(levels.participation_below).toBe('65.50')
    seedMockSession(SULTAN)
    expect((await call('GET', '/settings/certificates')).status).toBe(403)
  })
})
