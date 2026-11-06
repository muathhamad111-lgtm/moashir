import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useCertificateLevelsStore } from '@/stores/certificateLevels'
import { levelRanges } from '@/utils/levelRanges'

export function useCertificateLevels({ auto = true } = {}) {
  const store = useCertificateLevelsStore()
  const { levels, status } = storeToRefs(store)
  const ranges = computed(() => levelRanges(levels.value))

  if (auto) onMounted(() => store.ensureLoaded())

  return { ranges, status, load: () => store.load() }
}
