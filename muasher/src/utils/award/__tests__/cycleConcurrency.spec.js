import { describe, expect, it } from 'vitest'
import { exceedsConcurrency } from '../cycleConcurrency'

describe('exceedsConcurrency', () => {
  it('compares the running set with the chosen mode', () => {
    const twoRunningOneOpen = { running: 2, openForSubmission: 1 }
    const twoOpen = { running: 2, openForSubmission: 2 }

    expect(exceedsConcurrency('single', twoRunningOneOpen)).toBe(true)
    expect(exceedsConcurrency('single', { running: 1, openForSubmission: 1 })).toBe(false)
    expect(exceedsConcurrency('staggered', twoRunningOneOpen)).toBe(false)
    expect(exceedsConcurrency('staggered', twoOpen)).toBe(true)
    expect(exceedsConcurrency('concurrent', twoOpen)).toBe(false)
    expect(exceedsConcurrency('single', null)).toBe(false)
  })
})
