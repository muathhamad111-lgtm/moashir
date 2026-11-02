import { computed, ref } from 'vue'
import { accreditedService } from '@/services/accreditedService'
import { servicePricesService } from '@/services/servicePricesService'
import { useAuthStore } from '@/stores/auth'

export function useAccreditedIntro() {
  const auth = useAuthStore()
  const status = ref('loading')
  const errorMessage = ref('')
  const current = ref(null)
  const price = ref(null)
  const priceStatus = ref('loading')

  const isAuthenticated = computed(() => auth.isAuthenticated)
  const isEntity = computed(() => auth.isAuthenticated && Boolean(auth.user?.entityId))

  async function loadPrice() {
    priceStatus.value = 'loading'
    try {
      const { data } = await servicePricesService.getServicePrices()
      price.value = data.accredited
      priceStatus.value = data.accredited ? 'ready' : 'unset'
    } catch {
      price.value = null
      priceStatus.value = 'error'
    }
  }

  async function loadCurrent() {
    if (!isEntity.value) {
      current.value = null
      return
    }
    const { data } = await accreditedService.getCurrent()
    current.value = data
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    const priceLoad = loadPrice()
    try {
      await loadCurrent()
      status.value = 'ready'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
    await priceLoad
  }

  return { status, errorMessage, current, price, priceStatus, isAuthenticated, isEntity, load }
}
