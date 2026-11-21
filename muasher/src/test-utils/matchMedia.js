import { vi } from 'vitest'

const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)'
const MIN_WIDTH_PATTERN = /^\(min-width: (\d+)px\)$/
const NEGATED_PREFIX = 'not all and '

function createMediaQueryList(query, evaluate) {
  const listeners = new Set()
  let lastMatches = evaluate(query)
  return {
    media: query,
    get matches() {
      return evaluate(query)
    },
    listeners,
    addEventListener: (type, listener) => type === 'change' && listeners.add(listener),
    removeEventListener: (type, listener) => type === 'change' && listeners.delete(listener),
    notifyIfChanged() {
      const matches = evaluate(query)
      if (matches === lastMatches) return
      lastMatches = matches
      listeners.forEach((listener) => listener({ matches, media: query }))
    },
  }
}

export function stubMatchMedia({ width, reducedMotion = false }) {
  const state = { width, reducedMotion }
  const lists = []

  function evaluate(query) {
    if (query === REDUCED_MOTION_QUERY) return state.reducedMotion
    if (query.startsWith(NEGATED_PREFIX)) return !evaluate(query.slice(NEGATED_PREFIX.length))
    const minWidth = MIN_WIDTH_PATTERN.exec(query)
    return minWidth ? state.width >= Number(minWidth[1]) : false
  }

  vi.stubGlobal(
    'matchMedia',
    vi.fn((query) => {
      const list = createMediaQueryList(query, evaluate)
      lists.push(list)
      return list
    }),
  )

  return {
    resize(nextWidth) {
      state.width = nextWidth
      lists.forEach((list) => list.notifyIfChanged())
    },
    listenerCount: () => lists.reduce((total, list) => total + list.listeners.size, 0),
  }
}
