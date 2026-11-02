import { computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTable } from '@/composables/useTable'
import { useFilters } from '@/composables/useFilters'
import { usePagination } from '@/composables/usePagination'
import { useToast } from '@/composables/useToast'
import { useRegions } from '@/composables/useRegions'
import { regionsService } from '@/services/regionsService'
import { localizedText } from '@/utils/localizedText'

export function useCitiesAdmin() {
  const { t, locale } = useI18n()
  const toast = useToast()
  const regions = useRegions()
  const filters = useFilters({ regionId: '', active: '' })
  const paginator = usePagination({ perPage: 20 })
  const table = useTable({ fetcher: (params) => regionsService.listCities(params), filters, pagination: paginator })
  const editor = reactive({ open: false, city: null })

  const regionFilterOptions = computed(() => [{ value: '', label: t('entities.filters.allRegions') }, ...regions.regionOptions.value])
  const activeOptions = computed(() => [
    { value: '', label: t('entities.cities.allStates') },
    { value: 'true', label: t('status.active') },
    { value: 'false', label: t('status.inactive') },
  ])

  function regionName(regionId) {
    const region = regions.regions.value.find((item) => item.id === regionId)
    return localizedText(region?.name, locale.value)
  }

  function openEditor(city = null) {
    editor.city = city
    editor.open = true
  }

  async function toggle(city) {
    try {
      await regionsService.setCityActive(city.id, !city.isActive)
      toast.success(t(city.isActive ? 'entities.cities.toasts.deactivated' : 'entities.cities.toasts.activated'))
      await table.load()
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
    }
  }

  async function afterSave() {
    toast.success(t(editor.city ? 'entities.cities.toasts.updated' : 'entities.cities.toasts.created'))
    await Promise.all([table.load(), regions.load()])
  }

  onMounted(regions.load)

  return { ...table, filters, paginator, regions, editor, regionFilterOptions, activeOptions, regionName, openEditor, toggle, afterSave }
}
