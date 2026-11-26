import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  isIssuedSince,
  parseLevelAchievedSince,
  resolveLevelAchievedSince,
  warnInDevelopment,
} from '@/config/levelAchieved'

const RELEASE = '2026-11-06'
const RELEASE_START = Date.parse('2026-11-06T00:00:00+03:00')

afterEach(() => {
  vi.unstubAllEnvs()
  vi.restoreAllMocks()
})

describe('parseLevelAchievedSince', () => {
  it('reads an ISO 8601 date as the start of that day in Riyadh', () => {
    expect(parseLevelAchievedSince(RELEASE)).toBe(RELEASE_START)
    expect(parseLevelAchievedSince(` ${RELEASE} `)).toBe(RELEASE_START)
  })

  it.each([
    undefined,
    null,
    '',
    '   ',
    '06/11/2026',
    '2026-11-6',
    '2026-02-30',
    '2026-13-01',
    'soon',
  ])('treats %j as no cut-off', (value) => expect(parseLevelAchievedSince(value)).toBeNull())
})

describe('resolveLevelAchievedSince', () => {
  it('reports a missing or invalid setting and disables the dialog', () => {
    const onInvalid = vi.fn()
    expect(resolveLevelAchievedSince(undefined, onInvalid)).toBeNull()
    expect(resolveLevelAchievedSince('2026-02-30', onInvalid)).toBeNull()
    expect(onInvalid).toHaveBeenCalledTimes(2)
    expect(onInvalid).toHaveBeenLastCalledWith('2026-02-30')
  })

  it('stays silent for a valid setting', () => {
    const onInvalid = vi.fn()
    expect(resolveLevelAchievedSince(RELEASE, onInvalid)).toBe(RELEASE_START)
    expect(onInvalid).not.toHaveBeenCalled()
  })
})

describe('warnInDevelopment', () => {
  it('warns on the console in development', () => {
    vi.stubEnv('DEV', true)
    vi.stubEnv('MODE', 'development')
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    warnInDevelopment('soon')
    expect(warn).toHaveBeenCalledTimes(1)
    expect(warn.mock.calls[0][0]).toContain('VITE_LEVEL_ACHIEVED_SINCE')
  })

  it('stays silent in production builds', () => {
    vi.stubEnv('DEV', false)
    vi.stubEnv('MODE', 'production')
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    warnInDevelopment(undefined)
    expect(warn).not.toHaveBeenCalled()
  })
})

describe('isIssuedSince', () => {
  it('accepts a certificate issued on or after the cut-off', () => {
    expect(isIssuedSince('2026-11-05T21:00:00Z', RELEASE_START)).toBe(true)
    expect(isIssuedSince('2026-11-20T08:00:00Z', RELEASE_START)).toBe(true)
  })

  it('rejects a certificate issued before the cut-off', () => {
    expect(isIssuedSince('2026-11-05T20:59:59Z', RELEASE_START)).toBe(false)
    expect(isIssuedSince('2026-09-05T08:00:00Z', RELEASE_START)).toBe(false)
  })

  it('rejects everything when there is no cut-off or no issue date', () => {
    expect(isIssuedSince('2026-11-20T08:00:00Z', null)).toBe(false)
    expect(isIssuedSince(null, RELEASE_START)).toBe(false)
    expect(isIssuedSince('not a date', RELEASE_START)).toBe(false)
  })
})
