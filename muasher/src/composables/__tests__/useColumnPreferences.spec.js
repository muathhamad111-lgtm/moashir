import { afterEach, describe, expect, it } from 'vitest'
import { useColumnPreferences } from '@/composables/useColumnPreferences'

const OPTIONS = {
  storageKey: 'test.columns',
  toggleable: ['entity', 'total', 'receipt'],
  hiddenByDefault: ['receipt'],
}

afterEach(() => localStorage.clear())

describe('useColumnPreferences', () => {
  it('starts from the defaults and always shows the columns that cannot be turned off', () => {
    const columns = useColumnPreferences(OPTIONS)
    expect(columns.visible.value).toEqual(['entity', 'total'])
    expect(columns.isVisible('receipt')).toBe(false)
    expect(columns.isVisible('operation')).toBe(true)
  })

  it('remembers a choice in the browser, in the catalogue order', () => {
    useColumnPreferences(OPTIONS).setVisible('receipt', true)
    const again = useColumnPreferences(OPTIONS)
    expect(again.visible.value).toEqual(['entity', 'total', 'receipt'])
    again.setVisible('entity', false)
    expect(JSON.parse(localStorage.getItem('test.columns'))).toEqual(['total', 'receipt'])
    again.reset()
    expect(again.visible.value).toEqual(['entity', 'total'])
  })

  it('ignores an unreadable or unknown stored value', () => {
    localStorage.setItem('test.columns', '{broken')
    expect(useColumnPreferences(OPTIONS).visible.value).toEqual(['entity', 'total'])
    localStorage.setItem('test.columns', JSON.stringify(['ghost', 'total']))
    expect(useColumnPreferences(OPTIONS).visible.value).toEqual(['total'])
  })
})
