import { describe, expect, it } from 'vitest'
import {
  editableFooterLinks,
  editableHeaderLinks,
  publicFooterLinks,
  publicHeaderLinks,
} from '../content/siteMenus'

const HOME = { label: { ar: 'الرئيسية', en: 'Home' }, url: '/' }
const KPIS = { label: { ar: 'المؤشرات', en: '' }, url: '/kpis' }

describe('mock site menus', () => {
  it('treats links saved before the visibility switch as visible', () => {
    expect(editableFooterLinks([HOME])).toEqual([{ ...HOME, isVisible: true }])
    expect(editableHeaderLinks([HOME])).toEqual([{ ...HOME, isVisible: true, children: [] }])
  })

  it('serves only visible links and drops a group whose sub-links are all hidden', () => {
    const header = [
      { ...HOME, isVisible: false },
      {
        label: { ar: 'المزيد', en: 'More' },
        url: null,
        children: [KPIS, { ...HOME, isVisible: false }],
      },
      { label: { ar: 'فارغة', en: 'Empty' }, url: null, children: [{ ...KPIS, isVisible: false }] },
    ]
    expect(publicHeaderLinks(header)).toEqual([
      {
        label: { ar: 'المزيد', en: 'More' },
        url: null,
        children: [{ label: { ar: 'المؤشرات', en: 'المؤشرات' }, url: '/kpis' }],
      },
    ])
    expect(publicFooterLinks([{ ...HOME, isVisible: false }, KPIS])).toEqual([
      { label: { ar: 'المؤشرات', en: 'المؤشرات' }, url: '/kpis' },
    ])
  })
})
