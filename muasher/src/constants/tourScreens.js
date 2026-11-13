export const TOUR_SCREENS = Object.freeze(['register', 'path', 'evidence', 'review', 'result'])

export const TOUR_SCREEN_PATHS = Object.freeze({
  register: '/register',
  path: '/assessment/self',
  evidence: '/accredited',
  review: '/accredited',
  result: '/certificates',
})

export const TOUR_REGISTER_FIELDS = Object.freeze(['entityName', 'entityType', 'region', 'email'])
export const TOUR_PATH_TILES = Object.freeze(['self', 'accredited', 'advanced'])
export const TOUR_EVIDENCE_FILES = Object.freeze(['plan', 'policies', 'report'])
export const TOUR_REVIEW_ROWS = Object.freeze([
  { key: 'leadership', state: 'done' },
  { key: 'planning', state: 'done' },
  { key: 'people', state: 'review' },
])
export const TOUR_RESULT_SCORE = 84
