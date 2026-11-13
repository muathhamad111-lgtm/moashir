import { PROFILE_REQUIREMENTS, PROFILE_SECTION_STATES } from '@/constants/entityProfile'

const EMPTY_STATE = Object.freeze({
  state: PROFILE_SECTION_STATES.COMPLETE,
  missingCount: 0,
  missingFields: [],
})

function capitalize(text) {
  return text.charAt(0).toUpperCase() + text.slice(1)
}

export function fieldKeyOf(name) {
  return String(name)
    .split('.')
    .map((segment, index) => (index === 0 ? segment : capitalize(segment)))
    .join('')
}

export function fieldLabelKey(name) {
  return `portal.profile.fields.${fieldKeyOf(name)}`
}

function fieldView(field, missingFields) {
  return {
    name: field.name,
    requirement: field.requirement,
    required: field.requirement === PROFILE_REQUIREMENTS.REQUIRED,
    counted: field.requirement !== PROFILE_REQUIREMENTS.OPTIONAL,
    missing: missingFields.includes(field.name),
  }
}

export function sectionViewsOf(profile) {
  const states = new Map(
    (profile?.profileCompletion?.sections ?? []).map((section) => [section.key, section]),
  )
  return (profile?.profileSections ?? []).map((section) => {
    const state = states.get(section.key) ?? EMPTY_STATE
    return {
      key: section.key,
      revision: section.revision,
      state: state.state,
      missingCount: state.missingCount,
      missingFields: [...state.missingFields],
      fields: section.fields.map((field) => fieldView(field, state.missingFields)),
    }
  })
}

export function firstMissingOf(sections) {
  const section = sections.find((item) => item.missingFields.length > 0)
  return section ? { section: section.key, field: section.missingFields[0] } : null
}

export function nextMissingAfter(missingFields, current) {
  if (!missingFields.length) return null
  const index = missingFields.indexOf(current)
  return missingFields[(index + 1) % missingFields.length]
}
