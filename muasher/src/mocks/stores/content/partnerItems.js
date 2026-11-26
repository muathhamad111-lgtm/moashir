import { HTTPS_LINK_PATTERN } from '@/constants/cms'
import { createFieldErrors } from './contentValidation'

export const PARTNER_SECTION_TYPE = 'partners'
export const CMS_LOGO_PURPOSE = 'cms-logo'

const UNUSED_FIELDS = Object.freeze(['subtitle', 'description', 'ctaLabel', 'icon', 'meta'])
const LANGUAGES = Object.freeze(['ar', 'en'])

function has(body, field) {
  return Object.prototype.hasOwnProperty.call(body, field)
}

function isFilled(value) {
  if (value === null || value === undefined || value === '') return false
  return typeof value !== 'object' || Object.keys(value).length > 0
}

function checkName(errors, body, creating) {
  if (!creating && !has(body, 'title')) return
  LANGUAGES.forEach((language) =>
    errors.check(
      Boolean(String(body.title?.[language] ?? '').trim()),
      `title.${language}`,
      'required',
    ),
  )
}

export function isPartnerSection(section) {
  return section?.type === PARTNER_SECTION_TYPE
}

export function checkPartnerItem(body, { creating = false } = {}) {
  const errors = createFieldErrors()
  UNUSED_FIELDS.filter((field) => has(body, field) && isFilled(body[field])).forEach((field) =>
    errors.add(field, 'prohibited'),
  )
  checkName(errors, body, creating)
  if (creating || has(body, 'imageFileId'))
    errors.check(Boolean(body.imageFileId), 'imageFileId', 'required')
  if (isFilled(body.ctaUrl))
    errors.check(HTTPS_LINK_PATTERN.test(String(body.ctaUrl).trim()), 'ctaUrl', 'https')
  errors.throwIfAny()
}
