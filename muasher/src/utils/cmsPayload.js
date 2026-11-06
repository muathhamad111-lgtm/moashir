import { contentImageId } from './contentImage'
import { iconNameFromStored, storedIconFor } from './icons/storedIcon'

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

export function contentFormValues(record = null) {
  return {
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

export function contentPayload(values, record = null, { withBackground = false } = {}) {
  const payload = {
    ...Object.fromEntries(LOCALIZED_FIELDS.map((field) => [field, trimmed(values[field])])),
    ctaUrl: values.ctaUrl.trim() || null,
    icon: storedIconFor(values.icon),
    meta: mergedMeta(record?.meta, values.display),
    ...imageField(values, record),
  }
  if (withBackground) payload.background = values.background.trim() || null
  return payload
}

export function sectionLabel(section, localized) {
  return localized(section?.title) || section?.key || ''
}
