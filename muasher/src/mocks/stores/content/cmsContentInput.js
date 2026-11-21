import { CMS_BACKGROUND_PATTERN } from '@/constants/cms'
import { contentReject } from './contentRejection'
import { checkIcon, checkLink, createFieldErrors, normalizeLocalized } from './contentValidation'
import { imageOf, isUsableFile } from './publicFiles'

const LOCALIZED_FIELDS = ['title', 'subtitle', 'description', 'ctaLabel']
const LOCKED_FIELDS = ['id', 'key', 'type', 'order', 'items', 'enabled']
const CMS_IMAGE_PURPOSE = 'cms'

function has(body, field) {
  return Object.prototype.hasOwnProperty.call(body, field)
}

function isPlainObject(value) {
  return Object.prototype.toString.call(value) === '[object Object]'
}

function checkShape(errors, body, allow, withBackground) {
  LOCKED_FIELDS.filter((field) => !allow.includes(field) && has(body, field)).forEach((field) =>
    errors.add(field, 'prohibited'),
  )
  LOCALIZED_FIELDS.filter((field) => has(body, field)).forEach((field) =>
    errors.check(body[field] === null || isPlainObject(body[field]), `${field}.ar`, 'localized'),
  )
  checkLink(errors, body, 'ctaUrl')
  checkIcon(errors, body)
  if (has(body, 'meta'))
    errors.check(body.meta === null || isPlainObject(body.meta), 'meta', 'metaObject')
  if (withBackground && body.background)
    errors.check(CMS_BACKGROUND_PATTERN.test(body.background), 'background', 'background')
}

function imageChange(body) {
  if (!has(body, 'imageFileId')) return {}
  if (!body.imageFileId) return { image: null }
  if (!isUsableFile(body.imageFileId, CMS_IMAGE_PURPOSE))
    throw contentReject.fileNotUsable('imageFileId')
  return { image: imageOf(body.imageFileId) }
}

function blankToNull(value) {
  return value === '' || value === undefined ? null : value
}

export function readContentChanges(body, { allow = [], withBackground = false } = {}) {
  const errors = createFieldErrors()
  checkShape(errors, body, allow, withBackground)
  errors.throwIfAny()
  const changes = {}
  LOCALIZED_FIELDS.filter((field) => has(body, field)).forEach((field) => {
    changes[field] = normalizeLocalized(body[field])
  })
  const scalars = withBackground ? ['ctaUrl', 'icon', 'background'] : ['ctaUrl', 'icon']
  scalars
    .filter((field) => has(body, field))
    .forEach((field) => {
      changes[field] = blankToNull(
        typeof body[field] === 'string' ? body[field].trim() : body[field],
      )
    })
  if (has(body, 'meta'))
    changes.meta = body.meta && Object.keys(body.meta).length ? body.meta : null
  return { ...changes, ...imageChange(body) }
}

export { CMS_IMAGE_PURPOSE }
