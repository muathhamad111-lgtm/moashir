import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useRequestPathCountsStore } from '@/stores/requestPathCounts'
import { PATH_ICONS, PATH_NAV_KINDS } from '@/constants/paths'
import { assignedPathsOf, followedPathsOf, pathCountOf, selectedPathOf } from '@/utils/requestPaths'

const COUNT_OF = {
  [PATH_NAV_KINDS.QUEUE]: (entry) => entry.awaitingAssignment,
  [PATH_NAV_KINDS.ASSIGNED]: (entry) => entry.new + entry.inProgress,
}

export function useRequestPathNav() {
  const { t } = useI18n()
  const route = useRoute()
  const auth = useAuthStore()
  const counts = useRequestPathCountsStore()

  const sources = computed(() => ({
    [PATH_NAV_KINDS.QUEUE]: { paths: followedPathsOf(auth.user), counts: counts.queue },
    [PATH_NAV_KINDS.ASSIGNED]: {
      paths: assignedPathsOf(auth.user, counts.assigned),
      counts: counts.assigned,
    },
  }))

  function countLabelOf(kind, count) {
    return count === null ? '' : t(`nav.pathCounts.${kind}`, { count })
  }

  function entryOf(item, path) {
    const kind = item.pathEntries
    const entry = pathCountOf(sources.value[kind].counts, path)
    const count = entry ? COUNT_OF[kind](entry) : null
    const label = t(`nav.byPath.${kind}.${path}`)
    const countLabel = countLabelOf(kind, count)
    return {
      ...item,
      id: `${item.id}-${path}`,
      icon: PATH_ICONS[path],
      label,
      path,
      query: { path },
      count,
      accessibleLabel: countLabel ? `${label} — ${countLabel}` : label,
    }
  }

  function expand(items) {
    return items.flatMap((item) => {
      const paths = item.pathEntries ? sources.value[item.pathEntries].paths : []
      return paths.length ? paths.map((path) => entryOf(item, path)) : [item]
    })
  }

  function isActive(item) {
    if (route.name !== item.routeName) return false
    return selectedPathOf(route.query.path, sources.value[item.pathEntries].paths) === item.path
  }

  function refresh(kinds) {
    if (kinds.includes(PATH_NAV_KINDS.QUEUE)) counts.refreshQueue()
    if (kinds.includes(PATH_NAV_KINDS.ASSIGNED)) counts.refreshAssigned()
  }

  function follow(kinds) {
    watch(
      () => auth.user?.id ?? null,
      (userId) => {
        counts.claim(userId)
        if (userId !== null) refresh(kinds.value)
      },
      { immediate: true },
    )
  }

  return { expand, isActive, follow }
}
