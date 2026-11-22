import { describe, expect, it } from 'vitest'
import { checkPartnerItem, isPartnerSection } from '../partnerItems'

const NAME = { ar: 'وزارة الصحة', en: 'Ministry of Health' }

function fieldsOf(body, options) {
  try {
    checkPartnerItem(body, options)
    return []
  } catch (rejection) {
    return Object.keys(rejection.fields)
  }
}

describe('partner items in the mock CMS', () => {
  it('recognises only the partners section', () => {
    expect(isPartnerSection({ type: 'partners' })).toBe(true)
    expect(isPartnerSection({ type: 'paths' })).toBe(false)
  })

  it('accepts a named partner with a logo and an https link', () => {
    expect(
      fieldsOf(
        { title: NAME, imageFileId: 'logo', ctaUrl: 'https://www.moh.gov.sa' },
        { creating: true },
      ),
    ).toEqual([])
  })

  it('requires both names and a logo for a new partner', () => {
    expect(fieldsOf({ title: { ar: 'وزارة الصحة', en: '' } }, { creating: true })).toEqual([
      'title.en',
      'imageFileId',
    ])
  })

  it('refuses an http link, the generic fields and a removed logo', () => {
    expect(
      fieldsOf({
        ctaUrl: 'http://www.moh.gov.sa',
        icon: 'fa-solid fa-star',
        meta: { eyebrow: { ar: 'ش', en: 'p' } },
        imageFileId: null,
      }),
    ).toEqual(['icon', 'meta', 'imageFileId', 'ctaUrl'])
  })

  it('lets an edit leave the name and the logo untouched', () => {
    expect(fieldsOf({ ctaUrl: null, subtitle: null })).toEqual([])
  })
})
