import { PHONE_PATTERN } from '@/constants/entityEnums'
import { MockRejection } from '../contract/rejection'
import {
  blank,
  checkChoices,
  checkGrade,
  checkNumbers,
  checkPlace,
  collector,
  optionalCount,
  strategicPlanFrom,
} from './entityValidation'
import { applyRegistry, checkRegistry } from './entityRegistry'
import { checkUnifiedNumber } from './entityUnifiedNumber'
import { setMarketingConsent } from './entitiesStore'
import { sectionRevision } from './entityProfileSections'

const NAME_MAX = 200
const PERSON_NAME = { MIN: 3, MAX: 150 }

const SECTION_KEYS = Object.freeze({
  identity: ['name', 'unifiedNumber', 'licenseNumber', 'foundedOn'],
  classification: ['entityType', 'developmentSector', 'orgSize', 'regionId', 'cityId'],
  governance: ['governanceGrade', 'employeesCount', 'annualBeneficiariesCount', 'hasStrategicPlan'],
  contact: ['authorizedPersonName', 'phone', 'websiteUrl', 'marketingConsent'],
})

function checkPresent(errors, body, keys) {
  keys.filter((key) => !(key in body)).forEach((key) => errors.add(key, 'field.present'))
}

function checkRequired(errors, body, keys) {
  keys.filter((key) => blank(body[key])).forEach((key) => errors.add(key, 'field.required'))
}

function checkMax(errors, key, value, max) {
  if (!blank(value) && String(value).trim().length > max)
    errors.add(key, 'field.max_chars', { max })
}

function checkIdentity(errors, body, entity) {
  if (blank(body.name?.ar)) errors.add('name.ar', 'field.required')
  checkMax(errors, 'name.ar', body.name?.ar, NAME_MAX)
  checkMax(errors, 'name.en', body.name?.en, NAME_MAX)
  checkUnifiedNumber(errors, body, { entityId: entity.id, required: true })
  checkRegistry(errors, { licenseNumber: body.licenseNumber, foundedOn: body.foundedOn }, entity.id)
}

function checkClassification(errors, body) {
  checkRequired(errors, body, SECTION_KEYS.classification)
  checkChoices(errors, body)
  checkPlace(errors, body)
}

function checkGovernance(errors, body) {
  checkGrade(errors, body.governanceGrade)
  checkNumbers(errors, body)
  const plan = body.hasStrategicPlan
  if (plan !== null && plan !== undefined && typeof plan !== 'boolean')
    errors.add('hasStrategicPlan', 'field.invalid_choice')
}

function checkContact(errors, body) {
  const name = String(body.authorizedPersonName ?? '').trim()
  if (!name) errors.add('authorizedPersonName', 'field.required')
  else if (name.length < PERSON_NAME.MIN || name.length > PERSON_NAME.MAX)
    errors.add('authorizedPersonName', 'field.person_name')
  if (!blank(body.phone) && !PHONE_PATTERN.test(String(body.phone)))
    errors.add('phone', 'field.phone')
  if (typeof body.marketingConsent !== 'boolean') errors.add('marketingConsent', 'field.required')
  if ('officialEmail' in body) errors.add('officialEmail', 'field.official_email_prohibited')
  checkRegistry(errors, { websiteUrl: body.websiteUrl }, null)
}

const CHECKS = {
  identity: checkIdentity,
  classification: checkClassification,
  governance: checkGovernance,
  contact: checkContact,
}

export function assertSection(section, body, entity) {
  const errors = collector()
  if (blank(body.baseRevision)) errors.add('baseRevision', 'field.required')
  checkPresent(errors, body, SECTION_KEYS[section])
  if (Object.keys(errors.fields).length === 0) CHECKS[section](errors, body, entity)
  errors.assertValid()
}

export function assertFreshRevision(section, body, entity) {
  const current = sectionRevision(entity, section)
  if (body.baseRevision === current) return
  throw new MockRejection(409, 'profile.section_stale', { meta: { section, revision: current } })
}

function nullableText(value) {
  return blank(value) ? null : String(value).trim()
}

function localPhone(value) {
  const phone = nullableText(value)
  return phone && phone.startsWith('05') ? `+966${phone.slice(1)}` : phone
}

const APPLY = {
  identity(entity, body) {
    entity.name = { ar: String(body.name.ar).trim(), en: nullableText(body.name.en) }
    entity.unifiedNumber = String(body.unifiedNumber).trim()
    applyRegistry(entity, { licenseNumber: body.licenseNumber, foundedOn: body.foundedOn })
  },
  classification(entity, body) {
    entity.entityType = body.entityType
    entity.developmentSector = body.developmentSector
    entity.orgSize = body.orgSize
    entity.regionId = Number(body.regionId)
    entity.cityId = Number(body.cityId)
  },
  governance(entity, body) {
    entity.governanceGrade = blank(body.governanceGrade)
      ? null
      : Number(body.governanceGrade).toFixed(2)
    entity.employeesCount = optionalCount(body.employeesCount)
    entity.annualBeneficiariesCount = optionalCount(body.annualBeneficiariesCount)
    entity.hasStrategicPlan = strategicPlanFrom(body.hasStrategicPlan)
  },
  contact(entity, body) {
    const phone = localPhone(body.phone)
    if (entity.owner.phone !== phone) entity.owner.phoneVerifiedAt = null
    entity.owner.name = String(body.authorizedPersonName).trim()
    entity.owner.phone = phone
    applyRegistry(entity, { websiteUrl: body.websiteUrl })
    setMarketingConsent(entity, body.marketingConsent)
  },
}

export function applySection(section, entity, body) {
  APPLY[section](entity, body)
  return entity
}

export function snapshotOf(entity) {
  return { ...entity, name: { ...entity.name }, owner: { ...entity.owner } }
}
