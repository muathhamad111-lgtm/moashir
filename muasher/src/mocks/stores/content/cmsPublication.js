import { CMS_LIMITS } from '@/constants/cms'
import { findPage, nowIso, resetCmsState } from './cmsState'
import { contentReject } from './contentRejection'
import { createFieldErrors, normalizeLocalized } from './contentValidation'
import { pageResource, pageSnapshot } from './cmsSerializers'
import { resetPublicFiles } from './publicFiles'

const PROHIBITED = ['key', 'published', 'sections']

export function requirePage(key) {
  const page = findPage(key)
  if (!page) throw contentReject.notFound()
  return page
}

function publish(page) {
  const snapshot = pageSnapshot(page)
  if (!snapshot.sections.length) throw contentReject.pageEmpty()
  page.snapshot = snapshot
  page.published = true
  page.publishedAt = nowIso()
  page.hasDraftChanges = false
}

export function publishPage(key) {
  const page = requirePage(key)
  publish(page)
  return pageResource(page)
}

export function unpublishPage(key) {
  const page = requirePage(key)
  if (!page.published) throw contentReject.illegalTransition()
  page.published = false
  return pageResource(page)
}

function checkDetails(body) {
  const errors = createFieldErrors()
  PROHIBITED.filter((field) => body[field] !== undefined).forEach((field) =>
    errors.add(field, 'prohibited'),
  )
  const limits = { 'title.ar': CMS_LIMITS.pageTitleMax, 'seo.title.ar': CMS_LIMITS.seoTitleMax }
  Object.entries(limits).forEach(([path, max]) => {
    const [root, ...rest] = path.split('.')
    const value = rest.length > 1 ? body[root]?.[rest[0]] : body[root]
    errors.check(
      !value || (String(value.ar ?? '').length <= max && String(value.en ?? '').length <= max),
      path,
      'localized',
    )
  })
  errors.throwIfAny()
}

export function updatePageDetails(key, body) {
  const page = requirePage(key)
  checkDetails(body)
  if (body.title !== undefined) page.title = normalizeLocalized(body.title)
  if (body.seo !== undefined) {
    page.seo = {
      title: normalizeLocalized(body.seo?.title),
      description: normalizeLocalized(body.seo?.description),
    }
  }
  page.hasDraftChanges = true
  page.contentUpdatedAt = nowIso()
  return pageResource(page)
}

export function resetCmsMock() {
  resetPublicFiles()
  resetCmsState(publish)
}

resetCmsMock()
