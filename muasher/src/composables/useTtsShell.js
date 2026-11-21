import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { layoutKeyFor } from '@/utils/portalAudience'

const TTS_LAYOUTS = Object.freeze(['app', 'portal'])

export function useTtsShell() {
  const route = useRoute()
  const auth = useAuthStore()
  const layoutKey = computed(() => layoutKeyFor(route.meta.layout, auth.roles))
  const isTtsShell = computed(() => TTS_LAYOUTS.includes(layoutKey.value))
  return { layoutKey, isTtsShell }
}
