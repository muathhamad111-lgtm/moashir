import { describe, expect, it } from 'vitest'
import { awardFactRows, isAwardCertificate, toAwardFacts } from '@/utils/awardCertificate'

const t = (key) => key

describe('awardCertificate', () => {
  it('recognises the three award kinds only', () => {
    expect(isAwardCertificate({ kind: 'advanced_winner' })).toBe(true)
    expect(isAwardCertificate({ kind: 'advanced_participation' })).toBe(true)
    expect(isAwardCertificate({ kind: 'accredited' })).toBe(false)
    expect(isAwardCertificate(null)).toBe(false)
  })

  it('keeps only the facts a certificate has, ranks 1 to 3 only', () => {
    const award = toAwardFacts({
      cycleName: { ar: 'دورة', en: 'Cycle' },
      cycleYear: 2026,
      region: null,
      regionalRank: 4,
      nationalRank: null,
    })
    const rows = awardFactRows(award, { t, locale: 'en' })
    expect(rows.map((row) => row.key)).toEqual(['cycle'])
    expect(rows[0].value).toBe('Cycle 2026')
    expect(awardFactRows(null, { t, locale: 'en' })).toEqual([])
  })
})
