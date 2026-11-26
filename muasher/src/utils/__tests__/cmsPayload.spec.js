import { describe, expect, it } from 'vitest'
import { contentFormValues, contentPayload, mergedMeta } from '@/utils/cmsPayload'
import { kpiFormValues, kpiPayload } from '@/utils/kpiPayload'
import { moveIndex, sameOrder } from '@/utils/reorder'
import { formatKpiValue, kpiNumeric, kpiProgress } from '@/utils/kpiNumber'
import { isSafeLink } from '@/utils/safeLink'

const n = (value) => String(value)

describe('cmsPayload', () => {
  const section = {
    title: { ar: 'عنوان', en: '' },
    ctaUrl: '/register',
    icon: 'fa-solid fa-star',
    image: { fileId: 'f-1', url: '/api/v1/content/files/f-1' },
    meta: {
      preview: { score: 78 },
      eyebrow: { ar: 'تمهيد', en: 'Eyebrow' },
      role: 'secondary_cta',
    },
  }

  it('fills the form with empty languages and the display options', () => {
    const values = contentFormValues(section)
    expect(values.subtitle).toEqual({ ar: '', en: '' })
    expect(values.icon).toBe('star')
    expect(values.display).toEqual({
      eyebrow: { ar: 'تمهيد', en: 'Eyebrow' },
      availability: '',
      variant: '',
      role: 'secondary-cta',
    })
  })

  it('keeps unrelated meta, drops cleared display keys and sends the image only when it changed', () => {
    const values = {
      ...contentFormValues(section),
      ctaUrl: '  ',
      display: { eyebrow: { ar: '', en: '' }, availability: 'coming_soon', variant: '', role: '' },
    }
    const payload = contentPayload(values, section)
    expect(payload.ctaUrl).toBeNull()
    expect(payload.icon).toBe('fa-solid fa-star')
    expect(contentPayload({ ...values, icon: '' }, section).icon).toBeNull()
    expect(payload.meta).toEqual({ preview: { score: 78 }, availability: 'coming_soon' })
    expect(payload).not.toHaveProperty('imageFileId')
    expect(contentPayload({ ...values, image: null }, section).imageFileId).toBeNull()
    expect(contentPayload(values, section, { withBackground: true })).toHaveProperty(
      'background',
      null,
    )
    expect(mergedMeta(null, { eyebrow: { ar: '', en: '' } })).toBeNull()
  })
})

describe('kpiPayload', () => {
  it('sends decimal strings, null references and omits an empty slug', () => {
    const values = {
      ...kpiFormValues(),
      name: { ar: 'مؤشر', en: '' },
      value: ' 12.50 ',
      kpiCategoryId: '3',
    }
    const payload = kpiPayload(values)
    expect(payload).toMatchObject({
      value: '12.50',
      target: null,
      kpiCategoryId: 3,
      kpiTypeId: null,
      showOnHomepage: false,
    })
    expect(payload).not.toHaveProperty('slug')
    expect(payload).not.toHaveProperty('published')
    expect(payload).not.toHaveProperty('order')
    expect(payload.icon).toBe('fa-solid fa-chart-bar')
  })

  it('reads a stored icon as its platform icon name', () => {
    expect(kpiFormValues({ icon: 'fa-solid fa-people-group' }).icon).toBe('users-two')
    expect(kpiFormValues({ icon: null }).icon).toBe('chart-bar')
  })

  it('sends the image id only when the image changed', () => {
    const kpi = { id: 4, image: { fileId: 'k-1', url: '/x' } }
    expect(kpiPayload(kpiFormValues(kpi), kpi)).not.toHaveProperty('imageFileId')
    expect(kpiPayload({ ...kpiFormValues(kpi), image: { fileId: 'k-2' } }, kpi).imageFileId).toBe(
      'k-2',
    )
  })
})

describe('reorder, numbers and links', () => {
  it('moves entries and compares orders', () => {
    expect(moveIndex([1, 2, 3], 2, 0)).toEqual([3, 1, 2])
    expect(moveIndex([1, 2], 0, 5)).toEqual([1, 2])
    expect(sameOrder([1, 2], [1, 2])).toBe(true)
  })

  it('parses decimal strings and computes the bounded progress', () => {
    expect(kpiNumeric('71.5')).toBe(71.5)
    expect(kpiNumeric('')).toBeNull()
    expect(formatKpiValue(n, null)).toBe('')
    expect(kpiProgress('486', '700')).toBe(69)
    expect(kpiProgress('900', '700')).toBe(100)
    expect(kpiProgress('5', null)).toBeNull()
  })

  it('accepts only the server-safe link forms', () => {
    expect(
      ['/kpis', '#paths', 'mailto:info@moashir.example.sa', 'https://example.sa'].every(isSafeLink),
    ).toBe(true)
    expect(['javascript:alert(1)', 'http://example.sa', '//evil.example'].some(isSafeLink)).toBe(
      false,
    )
  })
})
