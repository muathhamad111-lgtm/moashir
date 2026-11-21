import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useFeaturedKpisStore } from '@/stores/featuredKpis'

export function useFeaturedKpis({ limit = null, auto = true } = {}) {
  const store = useFeaturedKpisStore()
  const { shown, status } = storeToRefs(store)
  const featured = computed(() => (limit ? shown.value.slice(0, limit) : shown.value))

  if (auto) onMounted(() => store.ensureLoaded())

  return { featured, status, load: () => store.load() }
}
