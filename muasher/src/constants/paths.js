import { ASSESSMENT_PATHS } from './assessment'

export const REVIEWED_PATHS = Object.freeze([
  ASSESSMENT_PATHS.ACCREDITED,
  ASSESSMENT_PATHS.ADVANCED,
])

// The managed paths in «إدارة المسارات», in tab order.
export const MANAGED_PATHS = Object.freeze([
  ASSESSMENT_PATHS.SELF,
  ASSESSMENT_PATHS.ACCREDITED,
  ASSESSMENT_PATHS.ADVANCED,
])

// Each path's row in the price list (the award is priced under «award»; the kit has its own row).
export const PATH_PRICE_KEYS = Object.freeze({
  [ASSESSMENT_PATHS.SELF]: 'self',
  [ASSESSMENT_PATHS.ACCREDITED]: 'accredited',
  [ASSESSMENT_PATHS.ADVANCED]: 'award',
})
export const KIT_PRICE_KEY = 'bag'

export const PATH_ICONS = Object.freeze({
  [ASSESSMENT_PATHS.SELF]: 'list-check',
  [ASSESSMENT_PATHS.ACCREDITED]: 'clipboard-check',
  [ASSESSMENT_PATHS.ADVANCED]: 'trophy',
  bag: 'briefcase',
})

export const ASSESSOR_QUALIFICATIONS = Object.freeze({
  ACCREDITED: 'accredited',
  ADVANCED_INITIAL: 'advanced_initial',
})

export const QUALIFICATION_PATHS = Object.freeze({
  [ASSESSOR_QUALIFICATIONS.ACCREDITED]: ASSESSMENT_PATHS.ACCREDITED,
  [ASSESSOR_QUALIFICATIONS.ADVANCED_INITIAL]: ASSESSMENT_PATHS.ADVANCED,
})

export const PATH_NAV_KINDS = Object.freeze({
  QUEUE: 'queue',
  ASSIGNED: 'assigned',
})

export const PATH_ENTITY_TEXTS = Object.freeze(['intro', 'instructions', 'afterSubmission'])

export const PATH_TEXT_MAX = 2000
export const PATH_DESCRIPTION_MAX = 500
