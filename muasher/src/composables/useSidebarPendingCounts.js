import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useActionCenterStore } from '@/stores/actionCenter'
import { isNavItemActive } from '@/constants/navigation'

function routeNameOf(router, link) {
  try {
    return router.resolve(link).name ?? null
  } catch {
    return null
  }
}

/**
 * The staff's pending work as count badges on the sidebar: each action-center item is
 * counted on the menu item its link opens. Items that carry their own count keep it.
 */
export function useSidebarPendingCounts() {
  const { t } = useI18n()
  const route = useRoute()
  const router = useRouter()
  const actionCenter = useActionCenterStore()

  const pending = computed(() =>
    actionCenter.items
      .map((item) => ({ routeName: routeNameOf(router, item.link), count: item.count }))
      .filter((item) => item.routeName && item.count > 0),
  )

  function countOf(navItem) {
    return pending.value
      .filter((item) => isNavItemActive(navItem, item.routeName))
      .reduce((sum, item) => sum + item.count, 0)
  }

  function withPendingCounts(items) {
    return items.map((item) => {
      if (item.count !== undefined) return item
      const count = countOf(item)
      if (!count) return item
      const label = t(item.labelKey)
      return { ...item, count, accessibleLabel: `${label} — ${t('nav.pendingCount', { count })}` }
    })
  }

  onMounted(() => actionCenter.ensureLoaded())
  watch(
    () => route.name,
    (name, previous) => {
      if (previous) actionCenter.load()
    },
  )

  return { withPendingCounts }
}
