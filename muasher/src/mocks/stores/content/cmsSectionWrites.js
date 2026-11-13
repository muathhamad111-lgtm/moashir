import { CMS_LIMITS, CMS_SECTION_KEY_PATTERN, CMS_SECTION_TYPES } from '@/constants/cms'
import {
  cmsState,
  findPage,
  findSection,
  nextId,
  resequence,
  sectionsOf,
  stamp,
  touchPage,
} from './cmsState'
import { contentReject } from './contentRejection'
import { createFieldErrors } from './contentValidation'
import { readContentChanges } from './cmsContentInput'
import { sectionResource } from './cmsSerializers'

function requirePage(pageKey) {
  const page = findPage(pageKey)
  if (!page) throw contentReject.notFound()
  return page
}

export function requireSection(sectionId) {
  const section = findSection(sectionId)
  if (!section) throw contentReject.notFound()
  return section
}

function checkNewSection(body, pageKey) {
  const errors = createFieldErrors()
  const key = typeof body.key === 'string' ? body.key.trim() : ''
  errors.check(
    key && key.length <= CMS_LIMITS.sectionKeyMax && CMS_SECTION_KEY_PATTERN.test(key),
    'key',
    'keyFormat',
  )
  errors.check(CMS_SECTION_TYPES.includes(body.type), 'type', 'required')
  errors.throwIfAny()
  const siblings = sectionsOf(pageKey)
  if (siblings.length >= CMS_LIMITS.sectionsPerPage)
    throw contentReject.limitReached(CMS_LIMITS.sectionsPerPage)
  if (siblings.some((section) => section.key === key)) throw contentReject.sectionKeyTaken()
  return key
}

export function createSection(pageKey, body) {
  requirePage(pageKey)
  const key = checkNewSection(body, pageKey)
  const changes = readContentChanges(body, {
    allow: ['key', 'type', 'enabled'],
    withBackground: true,
  })
  const section = stamp({
    id: nextId('section'),
    page: pageKey,
    key,
    type: body.type,
    enabled: body.enabled ?? true,
    order: sectionsOf(pageKey).length + 1,
    title: null,
    subtitle: null,
    description: null,
    ctaLabel: null,
    ctaUrl: null,
    icon: null,
    background: null,
    image: null,
    meta: null,
    items: [],
    ...changes,
  })
  cmsState.sections.push(section)
  touchPage(pageKey)
  return sectionResource(section)
}

export function updateSection(sectionId, body) {
  const section = requireSection(sectionId)
  Object.assign(section, readContentChanges(body, { withBackground: true }))
  stamp(section)
  touchPage(section.page)
  return sectionResource(section)
}

export function toggleSection(sectionId, body) {
  const section = requireSection(sectionId)
  if (typeof body.enabled !== 'boolean') throw contentReject.validation({})
  section.enabled = body.enabled
  stamp(section)
  touchPage(section.page)
  return sectionResource(section)
}

export function removeSection(sectionId) {
  const section = requireSection(sectionId)
  cmsState.sections = cmsState.sections.filter((entry) => entry.id !== section.id)
  resequence(sectionsOf(section.page))
  touchPage(section.page)
}

export function applyOrder(entries, orderedIds) {
  const ids = Array.isArray(orderedIds) ? orderedIds.map(Number) : []
  const complete =
    ids.length === entries.length &&
    new Set(ids).size === ids.length &&
    entries.every((entry) => ids.includes(entry.id))
  if (!complete) throw contentReject.orderIncomplete()
  entries.forEach((entry) => {
    entry.order = ids.indexOf(entry.id) + 1
  })
}

export function reorderSections(body) {
  const first = findSection(body.orderedIds?.[0])
  if (!first) throw contentReject.orderIncomplete()
  const siblings = sectionsOf(first.page)
  applyOrder(siblings, body.orderedIds)
  touchPage(first.page)
  return sectionsOf(first.page).map(sectionResource)
}
