import { computed, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { STORAGE_KEYS } from '@/constants/storageKeys'
import { readStored, writeStored } from '@/utils/safeStorage'

export function useLevelAchieved(certificate) {
  const auth = useAuthStore()
  const acknowledged = ref(null)
  const storageKey = computed(() => `${STORAGE_KEYS.levelAcknowledged}.${auth.user?.id ?? 'guest'}`)

  watch(storageKey, (key) => (acknowledged.value = readStored(key)), { immediate: true })

  const isOpen = computed(
    () => Boolean(certificate.value?.id) && acknowledged.value !== String(certificate.value.id),
  )

  function acknowledge() {
    if (!certificate.value?.id) return
    acknowledged.value = String(certificate.value.id)
    writeStored(storageKey.value, acknowledged.value)
  }

  return { isOpen, acknowledge }
}
