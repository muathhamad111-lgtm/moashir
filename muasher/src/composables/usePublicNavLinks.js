import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { usePublicHome } from '@/composables/useHomeContent'
import { HOME_ANCHORS, HOME_NAV_LINKS } from '@/constants/homeSections'
import { ROUTE_NAMES } from '@/constants/routeNames'

const EXTERNAL = /^(https?:\/\/|mailto:)/
const HOME_PATH = '/'

function anchorOf(url) {
  if (url === HOME_PATH) return HOME_ANCHORS.hero
  if (url.startsWith('#')) return url.slice(1)
  if (url.startsWith('/#')) return url.slice(2)
  return null
}

function homeTarget(anchor) {
  return anchor === HOME_ANCHORS.hero
    ? { name: ROUTE_NAMES.PUBLIC_HOME }
    : { name: ROUTE_NAMES.PUBLIC_HOME, hash: `#${anchor}` }
}

export function linkOf(url, label, key = url) {
  if (EXTERNAL.test(url)) return { key, label, href: url, anchor: null, to: null }
  const anchor = anchorOf(url)
  return { key, label, href: null, anchor, to: anchor ? homeTarget(anchor) : url }
}

function defaultLink(link, t, router) {
  if (link.routeName) {
    const path = router.resolve({ name: link.routeName }).fullPath
    return { ...linkOf(path, t(link.labelKey), link.id), to: { name: link.routeName } }
  }
  return linkOf(`/#${link.id}`, t(link.labelKey), link.id)
}

function managedLink(link, index, localized) {
  const label = localized(link.label)
  if (!link.children?.length) return linkOf(link.url, label, `${index}-${link.url}`)
  return {
    key: `group-${index}`,
    label,
    children: link.children.map((child, childIndex) =>
      linkOf(child.url, localized(child.label), `${index}-${childIndex}-${child.url}`),
    ),
  }
}

export function usePublicNavLinks() {
  const { t } = useI18n()
  const router = useRouter()
  const { siteSettings, localized } = usePublicHome({ auto: false })

  const links = computed(() => {
    const managed = siteSettings.value?.headerLinks
    if (!Array.isArray(managed)) return HOME_NAV_LINKS.map((link) => defaultLink(link, t, router))
    return managed.map((link, index) => managedLink(link, index, localized))
  })

  const spyAnchors = computed(() =>
    links.value
      .flatMap((link) => (link.children ? link.children : [link]))
      .map((link) => link.anchor)
      .filter(Boolean),
  )

  return { links, spyAnchors }
}
