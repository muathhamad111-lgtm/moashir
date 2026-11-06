import { ASSESSMENT_PATHS } from './assessment'

export const BAG_PATH = 'bag'

export const REVIEWED_PATHS = Object.freeze([
  ASSESSMENT_PATHS.ACCREDITED,
  ASSESSMENT_PATHS.ADVANCED,
])

export const PATH_ICONS = Object.freeze({
  [ASSESSMENT_PATHS.SELF]: 'list-check',
  [ASSESSMENT_PATHS.ACCREDITED]: 'clipboard-check',
  [ASSESSMENT_PATHS.ADVANCED]: 'trophy',
  [BAG_PATH]: 'briefcase',
})

export const ASSESSOR_QUALIFICATIONS = Object.freeze({
  ACCREDITED: 'accredited',
  ADVANCED_INITIAL: 'advanced_initial',
})

export const QUALIFICATION_PATHS = Object.freeze({
  [ASSESSOR_QUALIFICATIONS.ACCREDITED]: ASSESSMENT_PATHS.ACCREDITED,
  [ASSESSOR_QUALIFICATIONS.ADVANCED_INITIAL]: ASSESSMENT_PATHS.ADVANCED,
})

export const PATH_NAV_KINDS = Object.freeze({ QUEUE: 'queue', ASSIGNED: 'assigned' })

export const PATH_ENTITY_TEXTS = Object.freeze(['intro', 'instructions', 'afterSubmission'])

export const PATH_TEXT_MAX = 2000
