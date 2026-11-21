import { activeScenario } from './committee/committeeScenario'

// The main entity (Namaa) carries a complete profile. This scenario brings back the
// "complete your profile" journey for the screens and tests that walk through it.
export const PROFILE_INCOMPLETE_SCENARIO = 'profile-incomplete'

const INCOMPLETE_ENTITY_ID = 6001

const DETAIL_FIELDS = Object.freeze([
  'unifiedNumber',
  'licenseNumber',
  'foundedOn',
  'governanceGrade',
  'employeesCount',
  'annualBeneficiariesCount',
])

function withoutDetails(record) {
  return { ...record, ...Object.fromEntries(DETAIL_FIELDS.map((key) => [key, null])) }
}

export function applyProfileScenario(records) {
  if (activeScenario() !== PROFILE_INCOMPLETE_SCENARIO) return records
  return records.map((record) =>
    record.id === INCOMPLETE_ENTITY_ID ? withoutDetails(record) : record,
  )
}
