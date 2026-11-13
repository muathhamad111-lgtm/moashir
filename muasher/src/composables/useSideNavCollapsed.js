import { computed, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { STORAGE_KEYS } from '@/constants/storageKeys'
import { readStored, writeStored } from '@/utils/safeStorage'

const COLLAPSED = '1'
const EXPANDED = '0'

export function useSideNavCollapsed() {
  const auth = useAuthStore()
  const collapsed = ref(false)
  const storageKey = computed(() => `${STORAGE_KEYS.sideNavCollapsed}.${auth.user?.id ?? 'guest'}`)

  watch(storageKey, (key) => (collapsed.value = readStored(key) === COLLAPSED), {
    immediate: true,
  })

  function toggle() {
    collapsed.value = !collapsed.value
    writeStored(storageKey.value, collapsed.value ? COLLAPSED : EXPANDED)
  }

  return { collapsed, toggle }
}
