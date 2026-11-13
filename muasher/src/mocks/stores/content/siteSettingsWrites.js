import { SITE_SETTINGS_LIMITS, SOCIAL_PLATFORM_KEYS } from '@/constants/cms'
import { isSafeLink } from '@/utils/safeLink'
import { cmsState, nowIso } from './cmsState'
import { createFieldErrors, hasLocalizedText, normalizeLocalized } from './contentValidation'
import { siteSettingsResource } from './cmsSerializers'

const FIELDS = [
  'social',
  'contact',
  'footerNote',
  'quickLinks',
  'seo',
  'featuredKpiLimit',
  'showFeaturedKpis',
]
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const PHONE = /^\+?[0-9 ]{6,30}$/
const HTTPS = /^https:\/\/[^\s<>"]+$/

function checkSocial(errors, social) {
  if (!Array.isArray(social) || social.length > SITE_SETTINGS_LIMITS.socialMax)
    return errors.add('social', 'required')
  const seen = new Set()
  social.forEach((link, index) => {
    errors.check(
      SOCIAL_PLATFORM_KEYS.includes(link?.platform) && !seen.has(link.platform),
      `social.${index}.platform`,
      'required',
    )
    errors.check(HTTPS.test(link?.url ?? ''), `social.${index}.url`, 'https')
    seen.add(link?.platform)
  })
}

function checkQuickLinks(errors, links) {
  if (!Array.isArray(links) || links.length > SITE_SETTINGS_LIMITS.quickLinksMax)
    return errors.add('quickLinks', 'required')
  links.forEach((link, index) => {
    errors.check(hasLocalizedText(link?.label), `quickLinks.${index}.label.ar`, 'localized')
    errors.check(isSafeLink(link?.url ?? ''), `quickLinks.${index}.url`, 'linkSafe')
  })
}

function checkContact(errors, contact) {
  if (!contact) return
  if (contact.email) errors.check(EMAIL.test(contact.email), 'contact.email', 'email')
  if (contact.phone) errors.check(PHONE.test(contact.phone), 'contact.phone', 'required')
}

function checkLimit(errors, value) {
  const { featuredMin: min, featuredMax: max } = SITE_SETTINGS_LIMITS
  errors.check(
    Number.isInteger(value) && value >= min && value <= max,
    'featuredKpiLimit',
    'between',
    { min, max },
  )
}

function validate(body) {
  const errors = createFieldErrors()
  Object.keys(body)
    .filter((key) => !FIELDS.includes(key))
    .forEach((key) => errors.add(key, 'unknownKey'))
  if (body.social !== undefined) checkSocial(errors, body.social)
  if (body.quickLinks !== undefined) checkQuickLinks(errors, body.quickLinks)
  if (body.contact !== undefined) checkContact(errors, body.contact)
  if (body.featuredKpiLimit !== undefined) checkLimit(errors, body.featuredKpiLimit)
  errors.throwIfAny()
}

function normalized(body) {
  const changes = {}
  if (body.social !== undefined)
    changes.social = body.social.map(({ platform, url }) => ({ platform, url: url.trim() }))
  if (body.quickLinks !== undefined) {
    changes.quickLinks = body.quickLinks.map((link) => ({
      label: normalizeLocalized(link.label),
      url: link.url.trim(),
    }))
  }
  if (body.contact !== undefined) {
    changes.contact = body.contact
      ? {
          email: body.contact.email || null,
          phone: body.contact.phone || null,
          address: normalizeLocalized(body.contact.address),
        }
      : null
  }
  if (body.footerNote !== undefined) changes.footerNote = normalizeLocalized(body.footerNote)
  if (body.seo !== undefined) {
    changes.seo = {
      title: normalizeLocalized(body.seo?.title),
      description: normalizeLocalized(body.seo?.description),
    }
  }
  if (body.featuredKpiLimit !== undefined) changes.featuredKpiLimit = body.featuredKpiLimit
  if (body.showFeaturedKpis !== undefined) changes.showFeaturedKpis = Boolean(body.showFeaturedKpis)
  return changes
}

export function updateSiteSettings(body) {
  validate(body)
  Object.assign(cmsState.siteSettings, normalized(body), { updatedAt: nowIso() })
  return siteSettingsResource()
}

export function currentSiteSettings() {
  return cmsState.siteSettings
}
