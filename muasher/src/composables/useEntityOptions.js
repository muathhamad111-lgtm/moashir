import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { DEVELOPMENT_SECTORS, ENTITY_TYPES, ORG_SIZES } from '@/constants/entityEnums'
import { localizedText } from '@/utils/localizedText'

export function useEntityOptions() {
  const { t, locale } = useI18n()

  function localized(content) {
    return localizedText(content, locale.value)
  }

  function enumOptions(group, values) {
    return computed(() => values.map((value) => ({ value, label: t(`entities.enums.${group}.${value}`) })))
  }

  const strategicPlanOptions = computed(() => [
    { value: 'yes', label: t('common.yes') },
    { value: 'no', label: t('common.no') },
  ])

  return {
    localized,
    entityTypeOptions: enumOptions('entityType', ENTITY_TYPES),
    sectorOptions: enumOptions('developmentSector', DEVELOPMENT_SECTORS),
    orgSizeOptions: enumOptions('orgSize', ORG_SIZES),
    strategicPlanOptions,
  }
}
