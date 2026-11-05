import { ASSESSMENT_PATHS } from '@/constants/assessment'
import {
  QUEUE_STATES,
  STATES,
  hasAssessor,
  isAccreditedOpen,
  isInQueue,
  runsReviewClock,
} from '../accredited/accreditedStates'
import {
  ADVANCED_QUEUE_STATES,
  ADVANCED_STATES,
  hasAdvancedAssessor,
  isAdvancedOpen,
  isInAdvancedQueue,
  runsAdvancedClock,
} from '../award/advancedStates'

const PATHS = {
  [ASSESSMENT_PATHS.ACCREDITED]: {
    stages: {
      awaiting: STATES.SUBMITTED,
      assigned: STATES.ASSIGNED,
      reviewing: STATES.UNDER_REVIEW,
      done: STATES.COMPLETED,
    },
    queueStates: QUEUE_STATES,
    isOpen: isAccreditedOpen,
    inQueue: isInQueue,
    runsClock: runsReviewClock,
    hasAssessor,
    permission: 'assessment.accredited.manage',
    entityLink: '/assessment/accredited',
  },
  [ASSESSMENT_PATHS.ADVANCED]: {
    stages: {
      awaiting: ADVANCED_STATES.AWAITING_ASSIGNMENT,
      assigned: ADVANCED_STATES.ASSIGNED,
      reviewing: ADVANCED_STATES.UNDER_INITIAL_ASSESSMENT,
      done: ADVANCED_STATES.AWAITING_FILTERING,
    },
    queueStates: ADVANCED_QUEUE_STATES,
    isOpen: isAdvancedOpen,
    inQueue: isInAdvancedQueue,
    runsClock: runsAdvancedClock,
    hasAssessor: hasAdvancedAssessor,
    permission: 'assessment.advanced.manage',
    entityLink: '/assessment/advanced',
  },
}

export const REVIEWED_PATHS = Object.keys(PATHS)

export function isReviewedPath(path) {
  return Object.hasOwn(PATHS, path)
}

export function pathRules(path) {
  return PATHS[path]
}

export function stagesOf(record) {
  return PATHS[record.path].stages
}

export function isAdvanced(record) {
  return record.path === ASSESSMENT_PATHS.ADVANCED
}
