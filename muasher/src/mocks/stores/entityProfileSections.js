const REQUIRED = 'required'
const COMPLETION = 'completion'
const OPTIONAL = 'optional'
const PERCENT = 100
const REVISION_LENGTH = 32
const HASH_SEEDS = [0x811c9dc5, 0x01000193, 0x5bd1e995, 0x27d4eb2d]

function blank(value) {
  return value === null || value === undefined || String(value).trim() === ''
}

function field(key, requirement, read, { editable = true } = {}) {
  return { key, requirement, read, editable }
}

export const PROFILE_SECTION_CATALOG = Object.freeze([
  {
    key: 'identity',
    fields: [
      field('name.ar', REQUIRED, (entity) => entity.name?.ar ?? null),
      field('name.en', OPTIONAL, (entity) => entity.name?.en ?? null),
      field('unified_number', REQUIRED, (entity) => entity.unifiedNumber ?? null),
      field('license_number', COMPLETION, (entity) => entity.licenseNumber ?? null),
      field('founded_on', COMPLETION, (entity) => entity.foundedOn ?? null),
    ],
  },
  {
    key: 'classification',
    fields: [
      field('entity_type', REQUIRED, (entity) => entity.entityType ?? null),
      field('development_sector', REQUIRED, (entity) => entity.developmentSector ?? null),
      field('org_size', REQUIRED, (entity) => entity.orgSize ?? null),
      field('region_id', REQUIRED, (entity) => entity.regionId ?? null),
      field('city_id', REQUIRED, (entity) => entity.cityId ?? null),
    ],
  },
  {
    key: 'governance',
    fields: [
      field('governance_grade', COMPLETION, (entity) => entity.governanceGrade ?? null),
      field('employees_count', COMPLETION, (entity) => entity.employeesCount ?? null),
      field(
        'annual_beneficiaries_count',
        COMPLETION,
        (entity) => entity.annualBeneficiariesCount ?? null,
      ),
      field('has_strategic_plan', COMPLETION, (entity) => entity.hasStrategicPlan ?? null),
    ],
  },
  {
    key: 'contact',
    fields: [
      field('official_email', REQUIRED, (entity) => entity.owner?.email ?? null, {
        editable: false,
      }),
      field('authorized_person_name', REQUIRED, (entity) => entity.owner?.name ?? null),
      field('phone', OPTIONAL, (entity) => entity.owner?.phone ?? null),
      field('website_url', OPTIONAL, (entity) => entity.websiteUrl ?? null),
      field('marketing_consent', OPTIONAL, (entity) => entity.marketingConsentAt !== null),
    ],
  },
])

export const PROFILE_SECTION_KEYS = Object.freeze(
  PROFILE_SECTION_CATALOG.map((section) => section.key),
)

export function sectionOf(key) {
  return PROFILE_SECTION_CATALOG.find((section) => section.key === key) ?? null
}

function isCounted(entry) {
  return entry.requirement !== OPTIONAL
}

function hashWith(seed, text) {
  let hash = seed >>> 0
  for (let index = 0; index < text.length; index += 1) {
    hash ^= text.charCodeAt(index)
    hash = Math.imul(hash, 0x01000193) >>> 0
  }
  return hash.toString(16).padStart(8, '0')
}

export function sectionRevision(entity, key) {
  const values = sectionOf(key)
    .fields.filter((entry) => entry.editable)
    .map((entry) => [entry.key, entry.read(entity)])
  const text = JSON.stringify(values)
  return HASH_SEEDS.map((seed) => hashWith(seed, text))
    .join('')
    .slice(0, REVISION_LENGTH)
}

export function profileSectionsOf(entity) {
  return PROFILE_SECTION_CATALOG.map((section) => ({
    key: section.key,
    revision: sectionRevision(entity, section.key),
    fields: section.fields.map((entry) => ({ key: entry.key, requirement: entry.requirement })),
  }))
}

function missingOf(entity, section) {
  return section.fields
    .filter((entry) => isCounted(entry) && blank(entry.read(entity)))
    .map((entry) => entry.key)
}

export function profileCompletionOf(entity) {
  const sections = PROFILE_SECTION_CATALOG.map((section) => {
    const missingFields = missingOf(entity, section)
    return {
      key: section.key,
      state: missingFields.length ? 'incomplete' : 'complete',
      missingCount: missingFields.length,
      missingFields,
    }
  })
  const counted = PROFILE_SECTION_CATALOG.flatMap((section) => section.fields).filter(isCounted)
  const missing = sections.reduce((total, section) => total + section.missingCount, 0)
  const completedCount = counted.length - missing
  return {
    percent: Math.floor((completedCount / counted.length) * PERCENT),
    counted: counted.length,
    completedCount,
    sections,
  }
}

export function hasFullProfile(entity) {
  const { completedCount, counted } = profileCompletionOf(entity)
  return completedCount === counted
}

const HISTORY_ONLY_SECTIONS = Object.freeze({ name: 'identity', founding_year: 'identity' })

export function historySectionOf(field) {
  if (HISTORY_ONLY_SECTIONS[field]) return HISTORY_ONLY_SECTIONS[field]
  return (
    PROFILE_SECTION_CATALOG.find((section) => section.fields.some((entry) => entry.key === field))
      ?.key ?? null
  )
}
