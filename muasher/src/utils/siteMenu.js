import { MENU_LINK_TYPES, MENU_PAGE_TARGETS } from '@/constants/siteMenu'

function bilingual(value) {
  return { ar: value?.ar ?? '', en: value?.en ?? '' }
}

function trimmed(value) {
  return { ar: value.ar.trim(), en: value.en.trim() }
}

export function linkTypeOf(url) {
  return MENU_PAGE_TARGETS.some((target) => target.url === url)
    ? MENU_LINK_TYPES.page
    : MENU_LINK_TYPES.custom
}

export function newMenuLink() {
  const url = MENU_PAGE_TARGETS[0].url
  return { label: { ar: '', en: '' }, url, linkType: linkTypeOf(url), isVisible: true }
}

function linkDraftOf(row) {
  const url = row?.url ?? ''
  return {
    label: bilingual(row?.label),
    url,
    linkType: linkTypeOf(url),
    isVisible: row?.isVisible !== false,
  }
}

function linkPayloadOf(row) {
  return { label: trimmed(row.label), url: row.url.trim(), isVisible: Boolean(row.isVisible) }
}

export function footerDraftOf(rows) {
  return (rows ?? []).map(linkDraftOf)
}

export function headerDraftOf(rows) {
  return (rows ?? []).map((row) => ({
    ...linkDraftOf(row),
    children: footerDraftOf(row?.children),
  }))
}

export function footerPayloadOf(rows) {
  return rows.map(linkPayloadOf)
}

export function headerPayloadOf(rows) {
  return rows.map((row) => {
    const children = footerPayloadOf(row.children ?? [])
    const link = linkPayloadOf(row)
    return { ...link, url: children.length ? null : link.url, children }
  })
}

export function isMenuGroup(row) {
  return Array.isArray(row?.children) && row.children.length > 0
}
