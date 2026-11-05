import { describe, expect, it } from 'vitest'
import { catalogFormValues, catalogPayload, kpiCatalogSchema } from '@/utils/kpiCatalogSchema'

const t = (key) => key

describe('kpiCatalogSchema', () => {
  it('reads the stored category icon as a platform icon name', () => {
    expect(catalogFormValues({ key: 'impact', icon: 'fa-solid fa-hand-holding-heart' }).icon).toBe(
      'heart',
    )
    expect(catalogFormValues(null).icon).toBe('')
  })

  it('sends a category icon in the stored format and clears an empty one', () => {
    const values = {
      ...catalogFormValues(null),
      key: 'impact',
      name: { ar: 'الأثر', en: 'Impact' },
    }
    expect(catalogPayload('categories', { ...values, icon: 'target' }).icon).toBe(
      'fa-solid fa-target',
    )
    expect(catalogPayload('categories', values).icon).toBeNull()
    expect(catalogPayload('types', { ...values, icon: 'target' })).not.toHaveProperty('icon')
  })

  it('accepts only icons of the platform set', async () => {
    const schema = kpiCatalogSchema(t, 'categories')
    const base = { key: 'impact', name: { ar: 'الأثر', en: '' }, description: { ar: '', en: '' } }
    await expect(schema.validate({ ...base, icon: 'award' })).resolves.toBeTruthy()
    await expect(schema.validate({ ...base, icon: '' })).resolves.toBeTruthy()
    await expect(schema.validate({ ...base, icon: 'fa-solid fa-award' })).rejects.toThrow(
      'cms.validation.icon',
    )
  })
})
