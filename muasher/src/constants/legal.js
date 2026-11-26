export const LEGAL_KINDS = Object.freeze({
  TERMS: 'terms',
  PRIVACY: 'privacy',
  ACCESSIBILITY: 'accessibility',
})

export const LEGAL_VERSIONED_KINDS = Object.freeze([LEGAL_KINDS.TERMS, LEGAL_KINDS.PRIVACY])

export const LEGAL_STATES = Object.freeze({
  LOADING: 'loading',
  AVAILABLE: 'available',
  MISSING: 'missing',
  ERROR: 'error',
})

export const RICH_TEXT_BLOCKS = Object.freeze({
  HEADING: 'heading',
  PARAGRAPH: 'paragraph',
  LIST: 'list',
  TABLE: 'table',
})
