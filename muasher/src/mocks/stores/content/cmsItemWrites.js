import { CMS_LIMITS } from '@/constants/cms'
import { findItem, nextId, resequence, stamp, touchPage } from './cmsState'
import { contentReject } from './contentRejection'
import { readContentChanges } from './cmsContentInput'
import { sectionResource } from './cmsSerializers'
import { applyOrder, requireSection } from './cmsSectionWrites'

function requireItem(section, itemId) {
  const item = findItem(section, itemId)
  if (!item) throw contentReject.notFound()
  return item
}

function changed(section) {
  stamp(section)
  touchPage(section.page)
  return sectionResource(section)
}

export function createItem(sectionId, body) {
  const section = requireSection(sectionId)
  if (section.items.length >= CMS_LIMITS.itemsPerSection)
    throw contentReject.limitReached(CMS_LIMITS.itemsPerSection)
  const changes = readContentChanges(body, { allow: ['enabled'] })
  section.items.push(
    stamp({
      id: nextId('item'),
      sectionId: section.id,
      enabled: body.enabled ?? true,
      order: section.items.length + 1,
      title: null,
      subtitle: null,
      description: null,
      ctaLabel: null,
      ctaUrl: null,
      icon: null,
      image: null,
      meta: null,
      ...changes,
    }),
  )
  return changed(section)
}

export function updateItem(sectionId, itemId, body) {
  const section = requireSection(sectionId)
  const item = requireItem(section, itemId)
  Object.assign(item, readContentChanges(body))
  stamp(item)
  return changed(section)
}

export function toggleItem(sectionId, itemId, body) {
  const section = requireSection(sectionId)
  const item = requireItem(section, itemId)
  if (typeof body.enabled !== 'boolean') throw contentReject.validation({})
  item.enabled = body.enabled
  stamp(item)
  return changed(section)
}

export function removeItem(sectionId, itemId) {
  const section = requireSection(sectionId)
  const item = requireItem(section, itemId)
  section.items = section.items.filter((entry) => entry.id !== item.id)
  resequence(section.items)
  changed(section)
}

export function reorderItems(sectionId, body) {
  const section = requireSection(sectionId)
  applyOrder(section.items, body.orderedIds)
  return changed(section)
}
