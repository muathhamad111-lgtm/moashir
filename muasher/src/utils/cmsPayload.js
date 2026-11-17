import { contentImageId } from './contentImage'
import { iconNameFromStored, storedIconFor } from './icons/storedIcon'
import { CMS_ITEM_META_FIELDS, CMS_SECTION_META_FIELDS } from '@/constants/cmsMetaFields'

const LOCALIZED_FIELDS = ['title', 'subtitle', 'description', 'ctaLabel']
const DISPLAY_KEYS = ['eyebrow', 'availability', 'variant', 'role']

function bilingual(value) {
  return { ar: value?.ar ?? '', en: value?.en ?? '' }
}

function trimmed(value) {
  return { ar: (value?.ar ?? '').trim(), en: (value?.en ?? '').trim() }
}

function hasText(value) {
  return Boolean(value && (value.ar || value.en))
}

export function displayOptionsOf(meta) {
  return {
    eyebrow: bilingual(meta?.eyebrow),
    availability: meta?.availability ?? '',
    variant: meta?.variant ?? '',
    role: meta?.role ? String(meta.role).replaceAll('_', '-') : '',
  }
}

const ALL_META_FIELDS = [
  ...Object.values(CMS_SECTION_META_FIELDS).flat(),
  ...Object.values(CMS_ITEM_META_FIELDS).flat(),
]

function formValueOf(field, stored) {
  if (field.kind === 'localized') return bilingual(stored)
  if (field.kind === 'choices') return Array.isArray(stored) ? [...stored] : []
  if (stored === null || stored === undefined) return ''
  return String(stored)
}

export function metaFieldValuesOf(meta) {
  return Object.fromEntries(
    ALL_META_FIELDS.map((field) => [field.key, formValueOf(field, meta?.[field.key])]),
  )
}

function storedValueOf(field, value) {
  if (field.kind === 'localized') {
    const text = trimmed(value)
    return hasText(text) ? text : null
  }
  if (field.kind === 'choices') return Array.isArray(value) && value.length ? [...value] : null
  if (field.kind === 'integer') return value === '' || value === null ? null : Number(value)
  return value || null
}

export function withMetaFields(meta, values, fields) {
  const next = { ...meta }
  fields.forEach((field) => {
    const stored = storedValueOf(field, values?.[field.key])
    if (stored === null) delete next[field.key]
    else next[field.key] = stored
  })
  return Object.keys(next).length ? next : null
}

export function contentFormValues(record = null) {
  return {
    metaFields: metaFieldValuesOf(record?.meta),
    ...Object.fromEntries(LOCALIZED_FIELDS.map((field) => [field, bilingual(record?.[field])])),
    ctaUrl: record?.ctaUrl ?? '',
    icon: iconNameFromStored(record?.icon),
    background: record?.background ?? '',
    image: record?.image ?? null,
    display: displayOptionsOf(record?.meta),
  }
}

export function mergedMeta(meta, display) {
  const next = { ...meta }
  DISPLAY_KEYS.forEach((key) => delete next[key])
  const eyebrow = trimmed(display.eyebrow)
  if (hasText(eyebrow)) next.eyebrow = eyebrow
  ;['availability', 'variant', 'role'].forEach((key) => {
    if (display[key]) next[key] = display[key]
  })
  return Object.keys(next).length ? next : null
}

function imageField(values, record) {
  const nextId = contentImageId(values.image)
  return nextId === contentImageId(record?.image) ? {} : { imageFileId: nextId }
}

export function contentPayload(
  values,
  record = null,
  { withBackground = false, metaFields = [] } = {},
) {
  const payload = {
    ...Object.fromEntries(LOCALIZED_FIELDS.map((field) => [field, trimmed(values[field])])),
    ctaUrl: values.ctaUrl.trim() || null,
    icon: storedIconFor(values.icon),
    meta: withMetaFields(mergedMeta(record?.meta, values.display), values.metaFields, metaFields),
    ...imageField(values, record),
  }
  if (withBackground) payload.background = values.background.trim() || null
  return payload
}

export function sectionLabel(section, localized) {
  return localized(section?.title) || section?.key || ''
}
