import { PROFILE_FIELD_LABELS, profileChangesFixture } from '../fixtures/profileChanges'
import { findCity, findRegion } from './regionsStore'
import { listRoles } from './permissionsStore'
import { actorDisplayName } from './actorName'
import { historySectionOf } from './entityProfileSections'

const PHONE_VISIBLE_DIGITS = 3
const PHONE_PREFIX = '+9665'

function maskedPhone(phone) {
  if (!phone) return null
  const text = String(phone)
  return `${PHONE_PREFIX}${'*'.repeat(text.length - PHONE_PREFIX.length - PHONE_VISIBLE_DIGITS)}${text.slice(-PHONE_VISIBLE_DIGITS)}`
}

const TRACKED_FIELDS = Object.freeze({
  name: (entity) => entity.name,
  entity_type: (entity) => entity.entityType,
  development_sector: (entity) => entity.developmentSector,
  org_size: (entity) => entity.orgSize,
  founding_year: (entity) => entity.foundingYear,
  region_id: (entity) => entity.regionId,
  city_id: (entity) => entity.cityId,
  governance_grade: (entity) => entity.governanceGrade,
  employees_count: (entity) => entity.employeesCount,
  annual_beneficiaries_count: (entity) => entity.annualBeneficiariesCount,
  has_strategic_plan: (entity) => entity.hasStrategicPlan,
  unified_number: (entity) => entity.unifiedNumber,
  license_number: (entity) => entity.licenseNumber,
  founded_on: (entity) => entity.foundedOn,
  authorized_person_name: (entity) => entity.owner?.name,
  phone: (entity) => maskedPhone(entity.owner?.phone),
  website_url: (entity) => entity.websiteUrl,
  marketing_consent: (entity) => entity.marketingConsentAt !== null,
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
  const changed = Object.entries(TRACKED_FIELDS).filter(
    ([, read]) => !same(read(before), read(after)),
  )
  for (const [field, read] of changed) {
    changes.push({
      id: nextId++,
      entityId: entity.id,
      field,
      oldValue: read(before) ?? null,
      newValue: read(after) ?? null,
      changedBy: isStaff ? 'staff' : 'entity',
      staff: isStaff ? staffOf(actor) : null,
      changedAt,
    })
  }
  return changed.map(([field]) => field)
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
    section: historySectionOf(change.field),
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

export function changesOf(entityId, section = null) {
  return changes
    .filter((change) => change.entityId === Number(entityId))
    .filter((change) => !section || historySectionOf(change.field) === section)
    .sort((left, right) => right.changedAt.localeCompare(left.changedAt) || right.id - left.id)
}
