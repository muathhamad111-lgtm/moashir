import { cmsState, findPage, sectionsOf } from './cmsState'
import { withFallback } from './contentValidation'

const CONTENT_FIELDS = [
  'title',
  'subtitle',
  'description',
  'ctaLabel',
  'ctaUrl',
  'icon',
  'image',
  'meta',
]

function byOrder(a, b) {
  return a.order - b.order
}

function clone(value) {
  return value === undefined ? null : JSON.parse(JSON.stringify(value))
}

function editorContent(record) {
  return Object.fromEntries(CONTENT_FIELDS.map((field) => [field, clone(record[field])]))
}

function publicContent(record) {
  return {
    title: withFallback(record.title),
    subtitle: withFallback(record.subtitle),
    description: withFallback(record.description),
    ctaLabel: withFallback(record.ctaLabel),
    ctaUrl: record.ctaUrl ?? null,
    icon: record.icon ?? null,
    image: clone(record.image),
    meta: record.meta && Object.keys(record.meta).length ? clone(record.meta) : null,
  }
}

export function itemResource(item) {
  return {
    id: item.id,
    sectionId: item.sectionId,
    enabled: item.enabled,
    order: item.order,
    ...editorContent(item),
    updatedAt: item.updatedAt,
  }
}

export function sectionResource(section) {
  return {
    id: section.id,
    page: section.page,
    key: section.key,
    type: section.type,
    enabled: section.enabled,
    order: section.order,
    ...editorContent(section),
    background: section.background ?? null,
    items: [...section.items].sort(byOrder).map(itemResource),
    updatedAt: section.updatedAt,
  }
}

export function pageSummary(page) {
  return {
    key: page.key,
    title: clone(page.title),
    published: page.published,
    publishedAt: page.publishedAt,
    hasUnpublishedChanges: !page.publishedAt || page.hasDraftChanges,
    contentUpdatedAt: page.contentUpdatedAt,
  }
}

export function pageResource(page) {
  return {
    ...pageSummary(page),
    seo: { title: clone(page.seo?.title), description: clone(page.seo?.description) },
    sections: sectionsOf(page.key).map(sectionResource),
  }
}

export function pageSnapshot(page) {
  return {
    key: page.key,
    title: withFallback(page.title),
    seo: { title: withFallback(page.seo?.title), description: withFallback(page.seo?.description) },
    sections: sectionsOf(page.key)
      .filter((section) => section.enabled)
      .map((section) => ({
        id: section.id,
        key: section.key,
        type: section.type,
        ...publicContent(section),
        background: section.background ?? null,
        items: [...section.items]
          .filter((item) => item.enabled)
          .sort(byOrder)
          .map((item) => ({ id: item.id, ...publicContent(item) })),
      })),
  }
}

export function siteSettingsResource() {
  const settings = clone(cmsState.siteSettings)
  const contact = settings.contact ?? {}
  return {
    ...settings,
    contact: {
      email: contact.email ?? null,
      phone: contact.phone ?? null,
      address: contact.address ?? null,
    },
  }
}

export function publicSiteSettings() {
  const settings = cmsState.siteSettings
  return {
    social: clone(settings.social),
    contact: {
      email: settings.contact?.email ?? null,
      phone: settings.contact?.phone ?? null,
      address: withFallback(settings.contact?.address),
    },
    footerNote: withFallback(settings.footerNote),
    quickLinks: settings.quickLinks.map((link) => ({
      label: withFallback(link.label),
      url: link.url,
    })),
    seo: {
      title: withFallback(settings.seo?.title),
      description: withFallback(settings.seo?.description),
    },
    showFeaturedKpis: settings.showFeaturedKpis,
    featuredKpiLimit: settings.featuredKpiLimit,
  }
}

export function publishedPage(key) {
  const page = findPage(key)
  if (!page?.published || !page.snapshot) return null
  return { ...clone(page.snapshot), publishedAt: page.publishedAt }
}
