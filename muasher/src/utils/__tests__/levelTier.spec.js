import { describe, expect, it } from 'vitest'
import {
  currentLevelCertificate,
  miniCardOf,
  showsReassessment,
  tierOf,
  tierPalette,
  tierRank,
  tierOfLevel,
  reassessmentOf,
  heroActionOf,
} from '@/utils/levelTier'
import { ROUTE_NAMES } from '@/constants/routeNames'

const certificate = (overrides = {}) => ({
  id: 'c1',
  level: 'gold',
  status: 'issued',
  issuedAt: '2026-10-30T08:00:00Z',
  ...overrides,
})
const open = (status) => ({ active: { id: 4, status } })

describe('tier mapping', () => {
  it('uses the level of the latest issued accredited certificate', () => {
    const latest = currentLevelCertificate([
      certificate({ id: 'old', level: 'silver', issuedAt: '2025-11-02T08:00:00Z' }),
      certificate({ id: 'new', level: 'gold' }),
    ])
    expect(latest.id).toBe('new')
    expect(tierOf({ certificate: latest })).toBe('gold')
  })

  it('ignores revoked, superseded and suspended certificates', () => {
    const latest = currentLevelCertificate([
      certificate({ id: 'revoked', level: 'diamond', status: 'revoked' }),
      certificate({ id: 'superseded', level: 'diamond', status: 'superseded' }),
      certificate({ id: 'suspended', level: 'diamond', status: 'suspended' }),
      certificate({ id: 'valid', level: 'bronze', issuedAt: '2024-01-01T08:00:00Z' }),
    ])
    expect(latest.id).toBe('valid')
  })

  it.each([
    [null, null, 'none'],
    [null, open('draft'), 'progress'],
    [null, open('under-review'), 'progress'],
    [null, open('completed'), 'none'],
    [certificate({ level: null }), null, 'participation'],
    [certificate({ level: 'bronze' }), null, 'bronze'],
    [certificate({ level: 'silver' }), null, 'silver'],
    [certificate({ level: 'diamond' }), open('submitted'), 'diamond'],
  ])('maps %o / %o to %s', (cert, accredited, tier) => {
    expect(tierOf({ certificate: cert, accredited })).toBe(tier)
  })

  it('shows re-assessment only when a level is held and an accredited request is open', () => {
    expect(showsReassessment({ certificate: certificate(), accredited: open('assigned') })).toBe(
      true,
    )
    expect(showsReassessment({ certificate: certificate(), accredited: null })).toBe(false)
    expect(showsReassessment({ certificate: null, accredited: open('assigned') })).toBe(false)
    expect(
      showsReassessment({ certificate: certificate({ level: null }), accredited: open('draft') }),
    ).toBe(false)
  })

  it.each([
    ['none', 'level.miniCard.start', ROUTE_NAMES.ASSESSMENT_ACCREDITED],
    ['progress', 'level.miniCard.follow', ROUTE_NAMES.ASSESSMENT_ACCREDITED],
    ['participation', 'level.miniCard.participation', ROUTE_NAMES.CERTIFICATES_INDEX],
    ['gold', 'level.miniCard.certificate', ROUTE_NAMES.CERTIFICATES_INDEX],
  ])('labels the mini-card for %s', (tier, actionKey, routeName) => {
    expect(miniCardOf(tier)).toMatchObject({ actionKey, routeName })
  })

  it('uses the eye icon for the certificate action only (P-11)', () => {
    expect(miniCardOf('gold').icon).toBe('eye')
    expect(miniCardOf('none').icon).toBeUndefined()
  })

  it('orders tiers and maps non-metal tiers to the neutral palette', () => {
    expect(tierRank('diamond')).toBeGreaterThan(tierRank('gold'))
    expect(tierRank('none')).toBe(0)
    expect(tierPalette('participation')).toBe('neutral')
    expect(tierPalette('silver')).toBe('silver')
  })
})

describe('tier from the server level field', () => {
  it('keeps metal levels and participation as sent', () => {
    expect(tierOfLevel('gold')).toBe('gold')
    expect(tierOfLevel('participation', true)).toBe('participation')
  })

  it('maps a missing level to none, or in progress with an open accredited request', () => {
    expect(tierOfLevel(null)).toBe('none')
    expect(tierOfLevel(null, true)).toBe('progress')
    expect(tierOfLevel('none', true)).toBe('progress')
  })

  it('marks re-assessment only for a metal level with an open request', () => {
    expect(reassessmentOf('silver', true)).toBe(true)
    expect(reassessmentOf('silver', false)).toBe(false)
    expect(reassessmentOf('participation', true)).toBe(false)
    expect(reassessmentOf('progress', true)).toBe(false)
  })
})

describe('hero action', () => {
  it.each([
    ['silver', 'valid', false, 'level.miniCard.certificate', ROUTE_NAMES.CERTIFICATES_INDEX],
    [
      'participation',
      'valid',
      false,
      'level.miniCard.participation',
      ROUTE_NAMES.CERTIFICATES_INDEX,
    ],
    ['gold', 'expiring_soon', false, 'level.miniCard.start', ROUTE_NAMES.ASSESSMENT_ACCREDITED],
    ['silver', 'expired', false, 'level.miniCard.start', ROUTE_NAMES.ASSESSMENT_ACCREDITED],
    ['silver', 'expired', true, 'level.miniCard.follow', ROUTE_NAMES.ASSESSMENT_ACCREDITED],
    ['none', null, false, 'level.miniCard.start', ROUTE_NAMES.ASSESSMENT_ACCREDITED],
    ['progress', null, true, 'level.miniCard.follow', ROUTE_NAMES.ASSESSMENT_ACCREDITED],
  ])(
    'maps %s / %s / open request %s to one action',
    (tier, validity, hasOpenRequest, key, route) => {
      expect(heroActionOf({ tier, validity, hasOpenRequest })).toMatchObject({
        actionKey: key,
        routeName: route,
      })
    },
  )
})
