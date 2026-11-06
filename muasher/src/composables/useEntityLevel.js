import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useEntityLevelStore } from '@/stores/entityLevel'
import { miniCardOf, showsReassessment, tierOf } from '@/utils/levelTier'

export function useEntityLevel({ auto = true } = {}) {
  const store = useEntityLevelStore()
  const { certificate, accredited, status } = storeToRefs(store)
  const sources = computed(() => ({
    certificate: certificate.value,
    accredited: accredited.value,
  }))
  const tier = computed(() => tierOf(sources.value))
  const reassessment = computed(() => showsReassessment(sources.value))
  const miniCard = computed(() => miniCardOf(tier.value))

  if (auto) onMounted(() => store.ensureLoaded())

  return { certificate, accredited, status, tier, reassessment, miniCard, load: () => store.load() }
}
