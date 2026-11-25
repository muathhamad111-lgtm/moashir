import { onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useActionCenterStore } from '@/stores/actionCenter'
import { usePendingNavCounts } from '@/composables/usePendingNavCounts'

/**
 * The sidebar's pending-work badges: the counts of `usePendingNavCounts`, plus keeping the
 * action center fresh (loaded once, reloaded on every route change). The sidebar stays mounted on
 * phones (only hidden), so the tab bar and «المزيد» read the same fresh counts.
 */
export function useSidebarPendingCounts() {
  const route = useRoute()
  const actionCenter = useActionCenterStore()
  const { withPendingCounts } = usePendingNavCounts()

  onMounted(() => actionCenter.ensureLoaded())
  watch(
    () => route.name,
    (name, previous) => {
      if (previous) actionCenter.load()
    },
  )

  return { withPendingCounts }
}
