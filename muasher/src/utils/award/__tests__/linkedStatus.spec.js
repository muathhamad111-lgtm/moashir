import { describe, expect, it } from 'vitest'
import { linkedStatus } from '../linkedStatus'

const ALLOWED = ['awaiting-filtering', 'not-qualified']

describe('linkedStatus', () => {
  it('accepts the wire and the SPA spelling of an offered status', () => {
    expect(linkedStatus('not_qualified', ALLOWED)).toBe('not-qualified')
    expect(linkedStatus('awaiting-filtering', ALLOWED)).toBe('awaiting-filtering')
  })

  it('ignores a missing, foreign or malformed value', () => {
    expect(linkedStatus(undefined, ALLOWED)).toBe('')
    expect(linkedStatus('approved', ALLOWED)).toBe('')
    expect(linkedStatus(['not_qualified'], ALLOWED)).toBe('')
  })
})
