import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

export function usePortalTab(values, fallback = values[0]) {
  const route = useRoute()
  const router = useRouter()

  const tab = computed({
    get: () => (values.includes(route.query.tab) ? route.query.tab : fallback),
    set: (value) => {
      if (!values.includes(value)) return
      router.replace({ query: { ...route.query, tab: value } })
    },
  })

  return { tab }
}
