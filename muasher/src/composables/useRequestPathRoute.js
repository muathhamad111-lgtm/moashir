import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { overdueFromLink, selectedPathOf, statusForPath } from '@/utils/requestPaths'

export function useRequestPathRoute(paths) {
  const route = useRoute()
  const router = useRouter()

  const path = computed(() => selectedPathOf(route.query.path, paths.value))
  const linkStatus = computed(() => statusForPath(route.query.status, path.value))
  const linkOverdue = computed(() => overdueFromLink(route.query.overdue))

  function dropLinkFilter(key) {
    if (!(key in route.query)) return
    const query = { ...route.query }
    delete query[key]
    router.replace({ query })
  }

  return { route, path, linkStatus, linkOverdue, dropLinkFilter }
}
