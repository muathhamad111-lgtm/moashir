import { SITE_SETTINGS_LIMITS, SOCIAL_PLATFORM_KEYS } from '@/constants/cms'
import { isSafeLink } from '@/utils/safeLink'
import { cmsState, nowIso } from './cmsState'
import { createFieldErrors, hasLocalizedText, normalizeLocalized } from './contentValidation'
import { siteSettingsResource } from './cmsSerializers'

const FIELDS = [
  'platformName',
  'platformDescription',
  'headerLinks',
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

function checkLink(errors, path, link) {
  errors.check(hasLocalizedText(link?.label), `${path}.label.ar`, 'localized')
  errors.check(isSafeLink(link?.url ?? ''), `${path}.url`, 'linkSafe')
}

function checkLinks(errors, field, links, max) {
  if (!Array.isArray(links) || links.length > max) return errors.add(field, 'required')
  links.forEach((link, index) => checkLink(errors, `${field}.${index}`, link))
}

function checkHeaderLinks(errors, links) {
  const { headerLinksMax, headerChildrenMax } = SITE_SETTINGS_LIMITS
  if (!Array.isArray(links) || links.length > headerLinksMax)
    return errors.add('headerLinks', 'required')
  links.forEach((link, index) => {
    const path = `headerLinks.${index}`
    const children = link?.children ?? []
    if (!children.length) return checkLink(errors, path, link)
    errors.check(hasLocalizedText(link?.label), `${path}.label.ar`, 'localized')
    checkLinks(errors, `${path}.children`, children, headerChildrenMax)
  })
}

function checkComplete(errors, field, value) {
  ;['ar', 'en'].forEach((locale) => {
    errors.check(String(value?.[locale] ?? '').trim() !== '', `${field}.${locale}`, 'required')
  })
}

function normalizedLinks(links) {
  return links.map((link) => ({
    label: normalizeLocalized(link.label),
    url: link.url.trim(),
    isVisible: link.isVisible !== false,
  }))
}

function normalizedHeaderLinks(links) {
  return links.map((link) => {
    const children = normalizedLinks(link.children ?? [])
    return {
      label: normalizeLocalized(link.label),
      url: children.length ? null : link.url.trim(),
      isVisible: link.isVisible !== false,
      children,
    }
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
  if (body.platformName !== undefined) checkComplete(errors, 'platformName', body.platformName)
  if (body.platformDescription !== undefined)
    checkComplete(errors, 'platformDescription', body.platformDescription)
  if (body.social !== undefined) checkSocial(errors, body.social)
  if (body.quickLinks !== undefined)
    checkLinks(errors, 'quickLinks', body.quickLinks, SITE_SETTINGS_LIMITS.quickLinksMax)
  if (body.headerLinks !== undefined) checkHeaderLinks(errors, body.headerLinks)
  if (body.contact !== undefined) checkContact(errors, body.contact)
  if (body.featuredKpiLimit !== undefined) checkLimit(errors, body.featuredKpiLimit)
  errors.throwIfAny()
}

function normalized(body) {
  const changes = {}
  if (body.social !== undefined)
    changes.social = body.social.map(({ platform, url }) => ({ platform, url: url.trim() }))
  if (body.quickLinks !== undefined) changes.quickLinks = normalizedLinks(body.quickLinks)
  if (body.headerLinks !== undefined) changes.headerLinks = normalizedHeaderLinks(body.headerLinks)
  if (body.platformName !== undefined) changes.platformName = normalizeLocalized(body.platformName)
  if (body.platformDescription !== undefined)
    changes.platformDescription = normalizeLocalized(body.platformDescription)
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
