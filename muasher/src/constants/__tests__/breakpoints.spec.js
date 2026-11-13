import { describe, expect, it } from 'vitest'
import { DESKTOP_QUERY, PHONE_QUERY, TABLET_UP_QUERY } from '@/constants/breakpoints'

describe('breakpoints', () => {
  it('builds the tablet-and-up query from the md breakpoint token', () => {
    expect(TABLET_UP_QUERY).toBe('(min-width: 768px)')
  })

  it('builds the phone query as the exact complement of the tablet query', () => {
    expect(PHONE_QUERY).toBe('not all and (min-width: 768px)')
  })

  it('builds the desktop query from the lg breakpoint token', () => {
    expect(DESKTOP_QUERY).toBe('(min-width: 1024px)')
  })
})
