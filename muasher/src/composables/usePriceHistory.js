import { ref } from 'vue'
import { pricesSettingsService } from '@/services/pricesSettingsService'
import { PRICE_HISTORY_PER_PAGE } from '@/constants/prices'

export function usePriceHistory(key) {
  const open = ref(false)
  const status = ref('idle')
  const items = ref([])
  const page = ref(1)
  const lastPage = ref(1)

  async function load(target = 1) {
    status.value = 'loading'
    try {
      const envelope = await pricesSettingsService.history(key, {
        page: target,
        perPage: PRICE_HISTORY_PER_PAGE,
      })
      items.value = envelope.data
      page.value = envelope.meta?.pagination?.page ?? target
      lastPage.value = envelope.meta?.pagination?.lastPage ?? 1
      status.value = items.value.length ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  function toggle() {
    open.value = !open.value
    if (open.value) load(1)
  }

  function refresh() {
    if (open.value) load(page.value)
  }

  return { open, status, items, page, lastPage, load, toggle, refresh }
}
