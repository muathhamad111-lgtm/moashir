import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { resetAssessmentSettings } from '@/mocks/stores/accredited/assessmentSettingsStore'
import { setMockScenario } from '@/mocks/stores/committee/committeeScenario'
import { MOCK_API_BASE, wireFetch } from '@/test-utils/mockApi'

export const PEOPLE = {
  SECRETARY: { id: 18, roles: ['system_admin', 'award_secretary'] },
  SYSTEM_ADMIN: { id: 1, roles: ['system_admin'] },
  SUPER_ADMIN: { id: 900, roles: [], superAdmin: true },
  FOLLOWER: { id: 9, roles: ['request_follower'] },
  FILTERING_OFFICER: { id: 13, roles: ['filtering_officer'] },
  CONTENT_OFFICER: { id: 20, roles: ['content_officer'] },
  DAVID: { id: 27, roles: ['assessor'] },
  MANAL: { id: 29, roles: ['assessor'] },
  OMAR: { id: 30, roles: ['assessor'] },
  LAYLA: { id: 31, roles: ['assessor'] },
  INITIAL_ASSESSOR: { id: 10, roles: ['assessor'] },
  RUWAD: { id: 6005, roles: ['entity'], entityId: 6005 },
}

export const REASON = 'إجراء معتمد من إدارة الجائزة بعد مراجعة أعمال اللجنة.'

export async function call(method, path, body, locale = 'en') {
  const init = { method, headers: { 'accept-language': locale } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null }
}

export const keys = (value) => Object.keys(value).sort()

export function resetWorld(scenario = null) {
  setMockScenario(scenario)
  resetCriteriaStore()
  resetEntitiesMock()
  resetAssessmentSettings()
  resetAssessmentsStore()
}

export function fullSheet(criteria, value = '8') {
  const sheet = { subs: {}, points: {} }
  criteria.mains.forEach((main) =>
    main.subs.forEach((sub) => {
      if (sub.evaluation_type === 'guiding_points')
        sub.points.forEach((point) => (sheet.points[point.id] = value))
      else sheet.subs[sub.id] = value
    }),
  )
  return sheet
}
