import { onMounted, ref } from 'vue'
import { servicePricesService } from '@/services/servicePricesService'
import { logger } from '@/utils/logger'

const PRICE_KEYS = Object.freeze({ self: 'self', accredited: 'accredited', advanced: 'award' })

export function useLandingPrices() {
  const paths = ref({})

  async function load() {
    try {
      const { data } = await servicePricesService.getServicePrices()
      paths.value = data?.paths ?? {}
    } catch (error) {
      logger.error(error, { area: 'landing.prices' })
      paths.value = {}
    }
  }

  function pricingOf(path) {
    return paths.value[PRICE_KEYS[path]] ?? null
  }

  onMounted(load)

  return { pricingOf, load }
}
