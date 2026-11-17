import { describe, expect, it } from 'vitest'
import { createI18n } from 'vue-i18n'
import ar from '@/i18n/locales/ar.json'
import en from '@/i18n/locales/en.json'
import {
  contentFormValues,
  contentPayload,
  metaFieldValuesOf,
  withMetaFields,
} from '@/utils/cmsPayload'
import { contentSchema, metaFieldsSchema } from '@/utils/cmsSchemas'
import {
  CMS_GLYPHS,
  CMS_ITEM_META_FIELDS,
  CMS_SECTION_META_FIELDS,
  metaFieldsFor,
} from '@/constants/cmsMetaFields'
import { LANDING_GLYPHS } from '@/constants/landingGlyphs'
import { CMS_ITEM_ROLES, CMS_SECTION_TYPES, CMS_SECTION_VARIANTS } from '@/constants/cms'

const { t } = createI18n({ legacy: false, locale: 'ar', messages: { ar, en } }).global

function keysOf(type, forItem = false) {
  return metaFieldsFor(type, forItem).map((field) => field.key)
}

describe('landing meta field map', () => {
  it('mirrors the backend schemas for the landing section types', () => {
    expect(keysOf('hero')).toEqual(['highlight', 'scoreCaption'])
    expect(keysOf('about')).toEqual(['highlight', 'keyWords', 'audienceLabel', 'goalsTitle'])
    expect(keysOf('levels')).toContain('sampleScore')
    expect(keysOf('paths', true)).toEqual(['path', 'costNote'])
    expect(keysOf('how_it_works', true)).toEqual(['tags', 'screen'])
    expect(keysOf('faq')).toEqual([])
  })

  it('offers exactly the drawn glyphs and knows the new types, variant and roles', () => {
    expect(CMS_GLYPHS).toEqual(Object.keys(LANDING_GLYPHS))
    expect(CMS_GLYPHS).toHaveLength(17)
    expect(CMS_SECTION_TYPES).toEqual(expect.arrayContaining(['about', 'levels']))
    expect(CMS_SECTION_VARIANTS.rich_text).toEqual(['cta', 'band'])
    expect(CMS_ITEM_ROLES.about).toEqual(['audience', 'goal'])
    expect(CMS_ITEM_ROLES.bag_promo).toEqual(['step', 'tool'])
  })

  it('has a label in both languages for every field and option', () => {
    const fields = [
      ...Object.values(CMS_SECTION_META_FIELDS),
      ...Object.values(CMS_ITEM_META_FIELDS),
    ].flat()
    for (const messages of [ar, en]) {
      fields.forEach((field) => {
        expect(messages.cms.metaFields[field.key]).toBeTruthy()
        ;(field.options ?? []).forEach((option) =>
          expect(messages.cms.metaOptions[field.key][option]).toBeTruthy(),
        )
      })
      expect(messages.cms.sectionTypes.about).toBeTruthy()
      expect(messages.cms.sectionTypes.levels).toBeTruthy()
    }
  })
})

describe('landing meta payload', () => {
  const fields = metaFieldsFor('levels', false)

  it('reads stored meta into form values for every kind', () => {
    const values = metaFieldValuesOf({
      highlight: { ar: 'تعكس', en: 'reflects' },
      sampleScore: 84,
      tags: ['accredited'],
      glyph: 'medal',
    })
    expect(values.highlight).toEqual({ ar: 'تعكس', en: 'reflects' })
    expect(values.sampleScore).toBe('84')
    expect(values.tags).toEqual(['accredited'])
    expect(values.glyph).toBe('medal')
    expect(values.costNote).toEqual({ ar: '', en: '' })
  })

  it('writes typed values, drops cleared ones and keeps the rest of the meta', () => {
    const meta = { eyebrow: { ar: 'أ', en: 'A' }, hint: { ar: 'قديم', en: 'Old' }, custom: 1 }
    const next = withMetaFields(
      meta,
      { highlight: { ar: ' مميز ', en: 'Bold' }, hint: { ar: '', en: '' }, sampleScore: '72' },
      fields,
    )
    expect(next).toEqual({
      eyebrow: { ar: 'أ', en: 'A' },
      custom: 1,
      highlight: { ar: 'مميز', en: 'Bold' },
      sampleScore: 72,
    })
    expect(withMetaFields(null, {}, [])).toBeNull()
  })

  it('sends the meta fields of the section type with the rest of the content', () => {
    const record = {
      title: { ar: 'مستويات', en: 'Levels' },
      meta: { variant: 'x', sampleScore: 84 },
    }
    const values = contentFormValues(record)
    values.metaFields.sampleScore = '65'
    values.metaFields.tags = ['all']
    const payload = contentPayload(values, record, { metaFields: fields })
    expect(payload.meta.sampleScore).toBe(65)
    expect(payload.meta).not.toHaveProperty('tags')
    const journey = contentPayload(values, record, {
      metaFields: metaFieldsFor('how_it_works', true),
    })
    expect(journey.meta.tags).toEqual(['all'])
  })
})

describe('landing meta validation', () => {
  it('requires both languages together and enforces lengths', async () => {
    const schema = metaFieldsSchema(t, metaFieldsFor('hero', false))
    await expect(schema.validate({ highlight: { ar: 'نص', en: '' } })).rejects.toThrow(
      t('cms.validation.bothLanguages'),
    )
    await expect(schema.validate({ highlight: { ar: '', en: '' } })).resolves.toBeTruthy()
    await expect(schema.validate({ highlight: { ar: 'أ'.repeat(121), en: 'a' } })).rejects.toThrow(
      t('cms.validation.maxChars', { max: 120 }),
    )
  })

  it('accepts only whole scores from 0 to 100', async () => {
    const schema = metaFieldsSchema(t, metaFieldsFor('levels', false))
    const range = t('cms.validation.range', { min: 0, max: 100 })
    await expect(schema.validate({ sampleScore: '101' })).rejects.toThrow(range)
    await expect(schema.validate({ sampleScore: '50.5' })).rejects.toThrow(range)
    await expect(schema.validate({ sampleScore: '0' })).resolves.toBeTruthy()
    await expect(schema.validate({ sampleScore: '' })).resolves.toBeTruthy()
  })

  it('accepts only known choices', async () => {
    const items = metaFieldsSchema(t, metaFieldsFor('how_it_works', true))
    await expect(items.validate({ tags: ['award'], screen: 'review' })).resolves.toBeTruthy()
    const unknown = t('validation.required')
    await expect(items.validate({ tags: ['vip'] })).rejects.toThrow(unknown)
    await expect(items.validate({ screen: 'dashboard' })).rejects.toThrow(unknown)
    const glyphs = metaFieldsSchema(t, metaFieldsFor('about', true))
    await expect(glyphs.validate({ glyph: 'rocket' })).rejects.toThrow(unknown)
  })

  it('is part of the content schema', async () => {
    const schema = contentSchema(t, metaFieldsFor('hero', false))
    const values = { ...contentFormValues(null), metaFields: { highlight: { ar: 'نص', en: '' } } }
    await expect(schema.validate(values)).rejects.toThrow(t('cms.validation.bothLanguages'))
  })
})
