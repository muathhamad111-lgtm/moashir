import { CMS_PAGE_ORDER } from '@/constants/cms'
import { homePageFixture, siteSettingsFixture } from '../../fixtures/content'
import { otherPagesFixture } from '../../fixtures/cmsPages'

const SEED_TIME = '2026-11-01T08:00:00Z'
const ID_START = Object.freeze({ section: 500, item: 5000 })

export const cmsState = { pages: [], sections: [], siteSettings: null, sequence: { ...ID_START } }

function clone(value) {
  return JSON.parse(JSON.stringify(value))
}

function nowIso() {
  return new Date().toISOString()
}

function seedItem(sectionId, item) {
  return { ...clone(item), sectionId, updatedAt: SEED_TIME }
}

function seedSection(pageKey, section) {
  return {
    ...clone(section),
    page: pageKey,
    updatedAt: SEED_TIME,
    items: section.items.map((item) => seedItem(section.id, item)),
  }
}

function seedPage(fixture) {
  return {
    id: CMS_PAGE_ORDER.indexOf(fixture.key) + 1,
    key: fixture.key,
    title: clone(fixture.title),
    seo: clone(fixture.seo ?? { title: null, description: null }),
    published: false,
    publishedAt: null,
    snapshot: null,
    hasDraftChanges: false,
    contentUpdatedAt: SEED_TIME,
    seedPublished: fixture.published,
  }
}

export function resetCmsState(publish) {
  const fixtures = [homePageFixture, ...otherPagesFixture]
  cmsState.pages = fixtures.map(seedPage).sort((a, b) => a.id - b.id)
  cmsState.sections = fixtures.flatMap((page) =>
    page.sections.map((section) => seedSection(page.key, section)),
  )
  cmsState.siteSettings = { ...clone(siteSettingsFixture), updatedAt: SEED_TIME }
  cmsState.sequence = { ...ID_START }
  cmsState.pages.filter((page) => page.seedPublished).forEach((page) => publish(page))
}

export function nextId(kind) {
  cmsState.sequence[kind] += 1
  return cmsState.sequence[kind]
}

export function findPage(key) {
  return cmsState.pages.find((page) => page.key === key) ?? null
}

export function sectionsOf(pageKey) {
  return cmsState.sections
    .filter((section) => section.page === pageKey)
    .sort((a, b) => a.order - b.order)
}

export function findSection(sectionId) {
  return cmsState.sections.find((section) => section.id === Number(sectionId)) ?? null
}

export function findItem(section, itemId) {
  return section.items.find((item) => item.id === Number(itemId)) ?? null
}

export function touchPage(pageKey) {
  const page = findPage(pageKey)
  page.contentUpdatedAt = nowIso()
  page.hasDraftChanges = true
}

export function resequence(entries) {
  entries
    .sort((a, b) => a.order - b.order)
    .forEach((entry, index) => {
      entry.order = index + 1
    })
}

export function stamp(record) {
  record.updatedAt = nowIso()
  return record
}

export { nowIso }
