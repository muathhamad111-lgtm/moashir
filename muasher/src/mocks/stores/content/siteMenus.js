import { withFallback } from './contentValidation'

function linkOf(link) {
  return { label: link.label, url: link.url, isVisible: link.isVisible !== false }
}

export function editableFooterLinks(links) {
  return (links ?? []).map(linkOf)
}

export function editableHeaderLinks(links) {
  return (links ?? []).map((link) => {
    const children = editableFooterLinks(link.children)
    return { ...linkOf(link), url: children.length ? null : link.url, children }
  })
}

function publicLink(link) {
  return { label: withFallback(link.label), url: link.url }
}

export function publicFooterLinks(links) {
  return editableFooterLinks(links)
    .filter((link) => link.isVisible)
    .map(publicLink)
}

export function publicHeaderLinks(links) {
  return editableHeaderLinks(links)
    .map((link) => ({ ...link, children: publicFooterLinks(link.children) }))
    .filter((link) => link.isVisible && (link.url !== null || link.children.length))
    .map((link) => ({ ...publicLink(link), children: link.children }))
}
