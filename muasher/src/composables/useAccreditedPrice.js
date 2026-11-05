import { computed, onMounted, ref } from 'vue'
import { servicePricesService } from '@/services/servicePricesService'

export function useAccreditedPrice({ auto = true } = {}) {
  const pricing = ref(null)
  const status = ref('idle')

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await servicePricesService.getServicePrices()
      pricing.value = data?.accredited ?? null
      status.value = 'success'
    } catch {
      pricing.value = null
      status.value = 'error'
    }
  }

  const priceBeforeVat = computed(() => {
    const value = Number(pricing.value?.priceBeforeVat)
    return Number.isFinite(value) && value > 0 ? value : null
  })

  if (auto) onMounted(load)

  return { priceBeforeVat, status, load }
}
