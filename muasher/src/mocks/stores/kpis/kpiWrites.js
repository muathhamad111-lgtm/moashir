import { SITE_SETTINGS_LIMITS } from '@/constants/cms'
import { KPI_REORDER } from '@/constants/kpis'
import { slugify } from '@/utils/slug'
import { contentReject } from '../content/contentRejection'
import { applyOrder } from '../content/cmsSectionWrites'
import { findKpi, kpiState, liveKpis, nextKpiId, resequenceKpis, todayKey } from './kpiState'
import { readKpiChanges } from './kpiInput'
import { kpiOrderEntry, kpiResource } from './kpiSerializers'

const FEATURED_MAX = SITE_SETTINGS_LIMITS.featuredMax

function nowIso() {
  return new Date().toISOString()
}

export function requireKpi(kpiId) {
  const kpi = findKpi(kpiId)
  if (!kpi) throw contentReject.notFound()
  return kpi
}

function assertSlugFree(slug, ownId) {
  const taken = [...kpiState.kpis, ...kpiState.archived].some(
    (kpi) => kpi.slug === slug && kpi.id !== ownId,
  )
  if (taken) throw contentReject.slugTaken()
}

function uniqueSlug(name, ownId) {
  const base = slugify(name?.en || '') || `kpi-${ownId}`
  let candidate = base
  for (
    let index = 2;
    kpiState.kpis.some((kpi) => kpi.slug === candidate && kpi.id !== ownId);
    index += 1
  ) {
    candidate = `${base}-${index}`
  }
  return candidate
}

function assertFeaturedRoom(changes, kpi) {
  if (!changes.showOnHomepage || kpi?.showOnHomepage) return
  const flagged = kpiState.kpis.filter((entry) => entry.showOnHomepage).length
  if (flagged >= FEATURED_MAX) throw contentReject.featuredLimitReached(FEATURED_MAX)
}

function recordToday(kpi, value) {
  const day = todayKey()
  kpi.history = [
    ...kpi.history.filter((entry) => entry.measuredOn !== day),
    { value, measuredOn: day },
  ]
  kpi.lastUpdate = nowIso()
}

function stampActor(kpi, actor) {
  kpi.updatedAt = nowIso()
  kpi.updatedById = actor.id
  kpi.updatedByName = actor.superAdmin ? null : actor.name
}

export function createKpi(body, actor) {
  const changes = readKpiChanges(body, { partial: false })
  assertFeaturedRoom(changes)
  const id = nextKpiId('kpi')
  const slug = changes.slug ?? uniqueSlug(changes.name, id)
  assertSlugFree(slug, id)
  const kpi = {
    id,
    slug,
    name: null,
    shortDescription: null,
    fullDescription: null,
    value: null,
    target: null,
    baseline: null,
    unit: null,
    kpiCategoryId: null,
    kpiTypeId: null,
    status: null,
    owner: null,
    dataSource: null,
    calcMethod: null,
    notes: null,
    measurementYear: null,
    lastUpdate: null,
    externalLink: null,
    icon: null,
    image: null,
    cardColorToken: null,
    badgeColorToken: null,
    showOnHomepage: false,
    published: false,
    publishedAt: null,
    order: kpiState.kpis.length + 1,
    createdAt: nowIso(),
    updatedAt: nowIso(),
    history: [],
    ...changes,
  }
  kpi.slug = slug
  if (kpi.value !== null) recordToday(kpi, kpi.value)
  stampActor(kpi, actor)
  kpiState.kpis.push(kpi)
  return kpiResource(kpi)
}

export function updateKpi(kpiId, body, actor) {
  const kpi = requireKpi(kpiId)
  const changes = readKpiChanges(body, { partial: true })
  assertFeaturedRoom(changes, kpi)
  if (changes.slug) assertSlugFree(changes.slug, kpi.id)
  const valueChanged = changes.value !== undefined && changes.value !== kpi.value
  Object.assign(kpi, changes)
  if (!kpi.slug) kpi.slug = uniqueSlug(kpi.name, kpi.id)
  if (valueChanged && kpi.value !== null) recordToday(kpi, kpi.value)
  stampActor(kpi, actor)
  return kpiResource(kpi)
}

export function archiveKpi(kpiId) {
  const kpi = requireKpi(kpiId)
  kpiState.kpis = kpiState.kpis.filter((entry) => entry.id !== kpi.id)
  kpiState.archived.push(kpi)
  resequenceKpis()
}

export function setKpiPublished(kpiId, body, actor) {
  const kpi = requireKpi(kpiId)
  if (typeof body.published !== 'boolean') throw contentReject.validation({})
  kpi.published = body.published
  kpi.publishedAt = body.published ? nowIso() : kpi.publishedAt
  stampActor(kpi, actor)
  return kpiResource(kpi)
}

export function moveKpi(kpiId, body) {
  const kpi = requireKpi(kpiId)
  if (!Object.values(KPI_REORDER).includes(body.direction)) throw contentReject.validation({})
  const ordered = liveKpis()
  const index = ordered.findIndex((entry) => entry.id === kpi.id)
  const target = index + (body.direction === KPI_REORDER.UP ? -1 : 1)
  if (target >= 0 && target < ordered.length) {
    const neighbour = ordered[target]
    ;[kpi.order, neighbour.order] = [neighbour.order, kpi.order]
  }
  return liveKpis().map(kpiOrderEntry)
}

export function reorderKpis(body) {
  applyOrder(kpiState.kpis, body.orderedIds)
  return liveKpis().map(kpiOrderEntry)
}
