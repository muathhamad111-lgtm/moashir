import { computed, watch } from 'vue'
import { useField } from 'vee-validate'

export function useRegionCityFields(cityOptionsFor) {
  const { value: regionId } = useField('regionId')
  const { value: cityId } = useField('cityId')

  const cityOptions = computed(() => cityOptionsFor(regionId.value))

  watch(regionId, () => {
    if (!cityOptions.value.some((option) => option.value === String(cityId.value)))
      cityId.value = ''
  })

  return { regionId, cityOptions }
}
