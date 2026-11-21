import { http } from '../contract/http'
import { envelope, noContent } from '../contract/responses'
import { contentReject, contentRoute, readJson } from '../stores/content/contentRejection'
import { CONTENT_PERMISSIONS, requireContentPermission } from '../stores/content/contentAccess'
import { findPage, sectionsOf } from '../stores/content/cmsState'
import {
  pageResource,
  pageSummary,
  publicSiteSettings,
  publishedPage,
  siteSettingsResource,
} from '../stores/content/cmsSerializers'
import {
  publishPage,
  requirePage,
  unpublishPage,
  updatePageDetails,
} from '../stores/content/cmsPublication'
import {
  createSection,
  removeSection,
  reorderSections,
  toggleSection,
  updateSection,
} from '../stores/content/cmsSectionWrites'
import {
  createItem,
  removeItem,
  reorderItems,
  toggleItem,
  updateItem,
} from '../stores/content/cmsItemWrites'
import { updateSiteSettings } from '../stores/content/siteSettingsWrites'
import { acceptImage } from '../stores/content/imageUploads'
import { CMS_IMAGE_PURPOSE } from '../stores/content/cmsContentInput'
import { cmsState } from '../stores/content/cmsState'
import { publicFilesHandlers } from './publicFiles'

const HOME = 'home'
const MAINTENANCE = { published: false, maintenance: true }

function canManage() {
  return requireContentPermission(CONTENT_PERMISSIONS.HOME)
}

function homePayload() {
  const home = publishedPage(HOME)
  if (!home) return MAINTENANCE
  return {
    published: true,
    maintenance: false,
    title: home.title,
    seo: home.seo,
    sections: home.sections,
    siteSettings: publicSiteSettings(),
    publishedAt: home.publishedAt,
  }
}

const publicHandlers = [
  http.get(
    '/api/v1/content/status',
    contentRoute(() => {
      const home = publishedPage(HOME)
      return envelope({ maintenance: home === null, publishedAt: home?.publishedAt ?? null })
    }),
  ),
  http.get(
    '/api/v1/content/home',
    contentRoute(() => envelope(homePayload())),
  ),
  http.get(
    '/api/v1/content/pages/:key',
    contentRoute(({ params }) => {
      if (!findPage(params.key)) throw contentReject.notFound()
      const page = publishedPage(params.key)
      if (!page) throw contentReject.pageNotPublished()
      return envelope(page)
    }),
  ),
]

const pageHandlers = [
  http.get(
    '/api/v1/admin/content/home',
    contentRoute(() => {
      canManage()
      return envelope(
        { ...pageResource(findPage(HOME)), siteSettings: siteSettingsResource() },
        { total: sectionsOf(HOME).length },
      )
    }),
  ),
  http.get(
    '/api/v1/admin/content/pages',
    contentRoute(() => {
      canManage()
      return envelope(cmsState.pages.map(pageSummary))
    }),
  ),
  http.get(
    '/api/v1/admin/content/pages/:key',
    contentRoute(({ params }) => {
      canManage()
      return envelope(pageResource(requirePage(params.key)))
    }),
  ),
  http.put(
    '/api/v1/admin/content/pages/:key',
    contentRoute(async ({ params, request }) => {
      canManage()
      return envelope(updatePageDetails(params.key, await readJson(request)))
    }),
  ),
  http.post(
    '/api/v1/admin/content/pages/:key/publish',
    contentRoute(({ params }) => {
      canManage()
      return envelope(publishPage(params.key))
    }),
  ),
  http.post(
    '/api/v1/admin/content/pages/:key/unpublish',
    contentRoute(({ params }) => {
      canManage()
      return envelope(unpublishPage(params.key))
    }),
  ),
  http.post(
    '/api/v1/admin/content/pages/:key/sections',
    contentRoute(async ({ params, request }) => {
      canManage()
      return envelope(createSection(params.key, await readJson(request)), {}, { status: 201 })
    }),
  ),
]

const sectionHandlers = [
  http.patch(
    '/api/v1/admin/content/sections/reorder',
    contentRoute(async ({ request }) => {
      canManage()
      return envelope(reorderSections(await readJson(request)))
    }),
  ),
  http.patch(
    '/api/v1/admin/content/sections/:sectionId/enabled',
    contentRoute(async ({ params, request }) => {
      canManage()
      return envelope(toggleSection(params.sectionId, await readJson(request)))
    }),
  ),
  http.patch(
    '/api/v1/admin/content/sections/:sectionId/items/reorder',
    contentRoute(async ({ params, request }) => {
      canManage()
      return envelope(reorderItems(params.sectionId, await readJson(request)))
    }),
  ),
  http.patch(
    '/api/v1/admin/content/sections/:sectionId/items/:itemId/enabled',
    contentRoute(async ({ params, request }) => {
      canManage()
      return envelope(toggleItem(params.sectionId, params.itemId, await readJson(request)))
    }),
  ),
  http.patch(
    '/api/v1/admin/content/sections/:sectionId',
    contentRoute(async ({ params, request }) => {
      canManage()
      return envelope(updateSection(params.sectionId, await readJson(request)))
    }),
  ),
  http.delete(
    '/api/v1/admin/content/sections/:sectionId',
    contentRoute(({ params }) => {
      canManage()
      removeSection(params.sectionId)
      return noContent()
    }),
  ),
  http.post(
    '/api/v1/admin/content/sections/:sectionId/items',
    contentRoute(async ({ params, request }) => {
      canManage()
      return envelope(createItem(params.sectionId, await readJson(request)), {}, { status: 201 })
    }),
  ),
  http.put(
    '/api/v1/admin/content/sections/:sectionId/items/:itemId',
    contentRoute(async ({ params, request }) => {
      canManage()
      return envelope(updateItem(params.sectionId, params.itemId, await readJson(request)))
    }),
  ),
  http.delete(
    '/api/v1/admin/content/sections/:sectionId/items/:itemId',
    contentRoute(({ params }) => {
      canManage()
      removeItem(params.sectionId, params.itemId)
      return noContent()
    }),
  ),
]

const settingsHandlers = [
  http.get(
    '/api/v1/admin/content/site-settings',
    contentRoute(() => {
      canManage()
      return envelope(siteSettingsResource())
    }),
  ),
  http.patch(
    '/api/v1/admin/content/site-settings',
    contentRoute(async ({ request }) => {
      canManage()
      return envelope(updateSiteSettings(await readJson(request)))
    }),
  ),
  http.post(
    '/api/v1/admin/content/images',
    contentRoute(async ({ request }) => {
      canManage()
      const { upload } = await acceptImage(request, CMS_IMAGE_PURPOSE)
      return envelope(upload, {}, { status: 201 })
    }),
  ),
]

export const contentHandlers = [
  ...publicHandlers,
  ...pageHandlers,
  ...sectionHandlers,
  ...settingsHandlers,
  ...publicFilesHandlers,
]
