import { PROFILE_FIELD_LABELS, profileChangesFixture } from '../fixtures/profileChanges'
import { findCity, findRegion } from './regionsStore'
import { listRoles } from './permissionsStore'
import { actorDisplayName } from './actorName'

const TRACKED_FIELDS = Object.freeze({
  name: 'name',
  entity_type: 'entityType',
  development_sector: 'developmentSector',
  org_size: 'orgSize',
  founding_year: 'foundingYear',
  region_id: 'regionId',
  city_id: 'cityId',
  governance_grade: 'governanceGrade',
  employees_count: 'employeesCount',
  annual_beneficiaries_count: 'annualBeneficiariesCount',
  has_strategic_plan: 'hasStrategicPlan',
  unified_number: 'unifiedNumber',
  license_number: 'licenseNumber',
  founded_on: 'foundedOn',
  website_url: 'websiteUrl',
})

const PLACE_FIELDS = { region_id: findRegion, city_id: findCity }

let changes = []
let nextId = 1

function clone(change) {
  return {
    ...change,
    staff: change.staff
      ? { ...change.staff, roles: change.staff.roles.map((role) => ({ ...role })) }
      : null,
  }
}

export function resetProfileChanges() {
  changes = profileChangesFixture.map(clone)
  nextId = Math.max(...changes.map((change) => change.id)) + 1
}

resetProfileChanges()

function same(left, right) {
  return JSON.stringify(left ?? null) === JSON.stringify(right ?? null)
}

function staffOf(user) {
  const labels = new Map(listRoles().map((role) => [role.key, role.label]))
  const roles = user.roles
    .filter((key) => labels.has(key))
    .map((key) => ({ key, label: { ...labels.get(key) } }))
  return { name: actorDisplayName(user), roles }
}

export function recordProfileChanges(entity, before, after, { actor, isStaff }) {
  const changedAt = new Date().toISOString()
  for (const [field, attribute] of Object.entries(TRACKED_FIELDS)) {
    if (same(before[attribute], after[attribute])) continue
    changes.push({
      id: nextId++,
      entityId: entity.id,
      field,
      oldValue: before[attribute] ?? null,
      newValue: after[attribute] ?? null,
      changedBy: isStaff ? 'staff' : 'entity',
      staff: isStaff ? staffOf(actor) : null,
      changedAt,
    })
  }
}

function presentedValue(field, value) {
  if (value === null || value === undefined) return null
  const finder = PLACE_FIELDS[field]
  if (!finder) return value && typeof value === 'object' ? { ...value } : value
  const place = finder(value)
  return place ? { id: place.id, code: place.code, name: { ...place.name } } : null
}

function baseItem(change) {
  return {
    id: change.id,
    field: change.field,
    fieldLabel: { ...PROFILE_FIELD_LABELS[change.field] },
    oldValue: presentedValue(change.field, change.oldValue),
    newValue: presentedValue(change.field, change.newValue),
    changedBy: change.changedBy,
    changedAt: change.changedAt,
  }
}

export function ownChangeResource(change) {
  return baseItem(change)
}

export function staffChangeResource(change) {
  const staff = change.changedBy === 'staff' ? change.staff : null
  return {
    ...baseItem(change),
    changedByStaff: staff
      ? {
          name: staff.name,
          roles: staff.roles.map((role) => ({ ...role, label: { ...role.label } })),
        }
      : null,
  }
}

export function changesOf(entityId) {
  return changes
    .filter((change) => change.entityId === Number(entityId))
    .sort((left, right) => right.changedAt.localeCompare(left.changedAt) || right.id - left.id)
}
