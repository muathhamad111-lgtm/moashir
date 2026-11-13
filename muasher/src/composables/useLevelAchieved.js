import { computed, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { STORAGE_KEYS } from '@/constants/storageKeys'
import { LEVEL_ACHIEVED_SINCE, isIssuedSince } from '@/config/levelAchieved'
import { readStored, writeStored } from '@/utils/safeStorage'

export function useLevelAchieved(certificate, { since = LEVEL_ACHIEVED_SINCE } = {}) {
  const auth = useAuthStore()
  const acknowledged = ref(null)
  const storageKey = computed(() => `${STORAGE_KEYS.levelAcknowledged}.${auth.user?.id ?? 'guest'}`)

  watch(storageKey, (key) => (acknowledged.value = readStored(key)), { immediate: true })

  const isNew = computed(() => isIssuedSince(certificate.value?.issuedAt, since))
  const isOpen = computed(
    () =>
      Boolean(certificate.value?.id) &&
      isNew.value &&
      acknowledged.value !== String(certificate.value.id),
  )

  function acknowledge() {
    if (!certificate.value?.id) return
    acknowledged.value = String(certificate.value.id)
    writeStored(storageKey.value, acknowledged.value)
  }

  return { isOpen, acknowledge }
}
