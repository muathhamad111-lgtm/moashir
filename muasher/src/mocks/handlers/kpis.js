import { http } from '../contract/http'
import { envelope, noContent } from '../contract/responses'
import { requestLocale } from '../contract/locale'
import { contentReject, contentRoute, readJson } from '../stores/content/contentRejection'
import { CONTENT_PERMISSIONS, requireContentPermission } from '../stores/content/contentAccess'
import { acceptImage } from '../stores/content/imageUploads'
import { findBySlug } from '../stores/kpis/kpiState'
import { kpiDetail, kpiResource, publicKpi } from '../stores/kpis/kpiSerializers'
import {
  FEATURED_MINIMUM,
  adminKpiPage,
  featuredKpis,
  kpiStats,
  publicKpiPage,
} from '../stores/kpis/kpiQueries'
import {
  archiveKpi,
  createKpi,
  moveKpi,
  reorderKpis,
  requireKpi,
  setKpiPublished,
  updateKpi,
} from '../stores/kpis/kpiWrites'
import { listKpiValues, recordKpiValue } from '../stores/kpis/kpiValues'
import { KPI_IMAGE_PURPOSE } from '../stores/kpis/kpiInput'
import {
  createCatalogEntry,
  listedCatalog,
  removeCatalogEntry,
  updateCatalogEntry,
} from '../stores/kpis/kpiCatalogWrites'

function canManage() {
  return requireContentPermission(CONTENT_PERMISSIONS.KPIS)
}

function queryOf(request) {
  return new URL(request.url).searchParams
}

const publicHandlers = [
  http.get(
    '/api/v1/kpis/featured',
    contentRoute(() => {
      const kpis = featuredKpis()
      return envelope(kpis.map(publicKpi), { minimum: FEATURED_MINIMUM, count: kpis.length })
    }),
  ),
  http.get(
    '/api/v1/kpis',
    contentRoute(({ request }) => {
      const { items, pagination } = publicKpiPage(queryOf(request), requestLocale(request))
      return envelope(items.map(publicKpi), { pagination })
    }),
  ),
  http.get(
    '/api/v1/kpis/:slug',
    contentRoute(({ params }) => {
      const kpi = findBySlug(params.slug)
      if (!kpi?.published) throw contentReject.notFound()
      return envelope(kpiDetail(kpi))
    }),
  ),
  http.get(
    '/api/v1/kpi-categories',
    contentRoute(() => envelope(listedCatalog('categories', { activeOnly: true }))),
  ),
  http.get(
    '/api/v1/kpi-types',
    contentRoute(() => envelope(listedCatalog('types', { activeOnly: true }))),
  ),
]

const adminHandlers = [
  http.get(
    '/api/v1/admin/kpis',
    contentRoute(({ request }) => {
      canManage()
      const { items, pagination } = adminKpiPage(queryOf(request), requestLocale(request))
      return envelope(items.map(kpiResource), { pagination, stats: kpiStats() })
    }),
  ),
  http.post(
    '/api/v1/admin/kpis',
    contentRoute(async ({ request }) => {
      const actor = canManage()
      return envelope(createKpi(await readJson(request), actor), {}, { status: 201 })
    }),
  ),
  http.patch(
    '/api/v1/admin/kpis/reorder',
    contentRoute(async ({ request }) => {
      canManage()
      return envelope(reorderKpis(await readJson(request)))
    }),
  ),
  http.post(
    '/api/v1/admin/kpis/images',
    contentRoute(async ({ request }) => {
      canManage()
      const { upload } = await acceptImage(request, KPI_IMAGE_PURPOSE)
      return envelope(upload, {}, { status: 201 })
    }),
  ),
  http.get(
    '/api/v1/admin/kpis/:kpiId',
    contentRoute(({ params }) => {
      canManage()
      return envelope(kpiResource(requireKpi(params.kpiId)))
    }),
  ),
  http.put(
    '/api/v1/admin/kpis/:kpiId',
    contentRoute(async ({ params, request }) => {
      const actor = canManage()
      return envelope(updateKpi(params.kpiId, await readJson(request), actor))
    }),
  ),
  http.delete(
    '/api/v1/admin/kpis/:kpiId',
    contentRoute(({ params }) => {
      canManage()
      archiveKpi(params.kpiId)
      return noContent()
    }),
  ),
  http.patch(
    '/api/v1/admin/kpis/:kpiId/published',
    contentRoute(async ({ params, request }) => {
      const actor = canManage()
      return envelope(setKpiPublished(params.kpiId, await readJson(request), actor))
    }),
  ),
  http.patch(
    '/api/v1/admin/kpis/:kpiId/reorder',
    contentRoute(async ({ params, request }) => {
      canManage()
      return envelope(moveKpi(params.kpiId, await readJson(request)))
    }),
  ),
  http.get(
    '/api/v1/admin/kpis/:kpiId/values',
    contentRoute(({ params }) => {
      canManage()
      return envelope(listKpiValues(params.kpiId))
    }),
  ),
  http.post(
    '/api/v1/admin/kpis/:kpiId/values',
    contentRoute(async ({ params, request }) => {
      canManage()
      return envelope(recordKpiValue(params.kpiId, await readJson(request)), {}, { status: 201 })
    }),
  ),
]

function catalogHandlers(kind, path) {
  return [
    http.get(
      `/api/v1/admin/${path}`,
      contentRoute(() => {
        canManage()
        return envelope(listedCatalog(kind))
      }),
    ),
    http.post(
      `/api/v1/admin/${path}`,
      contentRoute(async ({ request }) => {
        canManage()
        return envelope(createCatalogEntry(kind, await readJson(request)), {}, { status: 201 })
      }),
    ),
    http.put(
      `/api/v1/admin/${path}/:entryId`,
      contentRoute(async ({ params, request }) => {
        canManage()
        return envelope(updateCatalogEntry(kind, params.entryId, await readJson(request)))
      }),
    ),
    http.delete(
      `/api/v1/admin/${path}/:entryId`,
      contentRoute(({ params }) => {
        canManage()
        removeCatalogEntry(kind, params.entryId)
        return noContent()
      }),
    ),
  ]
}

export const kpisHandlers = [
  ...publicHandlers,
  ...adminHandlers,
  ...catalogHandlers('categories', 'kpi-categories'),
  ...catalogHandlers('types', 'kpi-types'),
]
