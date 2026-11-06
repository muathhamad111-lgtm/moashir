import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { statusLabelKey } from '@/utils/canonicalStatus'
import {
  DEVELOPMENT_SECTORS,
  ENTITY_SORTS,
  ENTITY_STATUSES,
  ENTITY_TYPES,
  ORG_SIZES,
} from '@/constants/entityEnums'
import { LEVEL_FILTER_VALUES } from '@/constants/levels'

export function useEntityFilterOptions(regionOptions) {
  const { t } = useI18n()

  function withAll(allKey, options) {
    return [{ value: '', label: t(`entities.filters.${allKey}`) }, ...options]
  }

  function enumOptions(group, values) {
    return values.map((value) => ({ value, label: t(`entities.enums.${group}.${value}`) }))
  }

  return {
    statusOptions: computed(() =>
      withAll(
        'allStatuses',
        Object.values(ENTITY_STATUSES).map((value) => ({ value, label: t(statusLabelKey(value)) })),
      ),
    ),
    regionFilterOptions: computed(() => withAll('allRegions', regionOptions.value)),
    typeOptions: computed(() => withAll('allTypes', enumOptions('entityType', ENTITY_TYPES))),
    sectorOptions: computed(() =>
      withAll('allSectors', enumOptions('developmentSector', DEVELOPMENT_SECTORS)),
    ),
    sizeOptions: computed(() => withAll('allSizes', enumOptions('orgSize', ORG_SIZES))),
    levelOptions: computed(() =>
      withAll(
        'allLevels',
        LEVEL_FILTER_VALUES.map((value) => ({ value, label: t(`level.short.${value}`) })),
      ),
    ),
    sortOptions: computed(() =>
      ENTITY_SORTS.map((value) => ({ value, label: t(`entities.sorts.${value}`) })),
    ),
  }
}
