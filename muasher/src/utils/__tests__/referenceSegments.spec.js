import { describe, expect, it } from 'vitest'
import { referenceSegments } from '@/utils/referenceSegments'

describe('reference segments', () => {
  it('isolates request, message and receipt references inside Arabic text', () => {
    expect(
      referenceSegments('ردّ فريق منصة مؤشر على استفساركم CNT-2026-000052').map((part) => [
        part.text,
        part.isReference,
      ]),
    ).toEqual([
      ['ردّ فريق منصة مؤشر على استفساركم ', false],
      ['CNT-2026-000052', true],
    ])
    expect(
      referenceSegments('ACC-2026-000014 و RCP-2026-000014').filter((part) => part.isReference),
    ).toHaveLength(2)
  })

  it('returns no segment for empty text', () => {
    expect(referenceSegments(null)).toEqual([])
  })
})
