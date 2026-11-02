import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { regionsService } from '@/services/regionsService'
import { localizedText } from '@/utils/localizedText'

export function useRegions() {
  const { locale } = useI18n()
  const regions = ref([])
  const status = ref('idle')

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await regionsService.listRegions()
      regions.value = data ?? []
      status.value = 'success'
    } catch {
      status.value = 'error'
    }
  }

  function option(item) {
    return { value: String(item.id), label: localizedText(item.name, locale.value) }
  }

  const regionOptions = computed(() => regions.value.map(option))

  function cityOptionsFor(regionId) {
    const region = regions.value.find((item) => String(item.id) === String(regionId))
    return region ? region.cities.map(option) : []
  }

  return { regions, status, load, regionOptions, cityOptionsFor }
}
