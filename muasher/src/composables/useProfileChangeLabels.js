import { useI18n } from 'vue-i18n'

const FIELD_LABEL_KEYS = {
  'name.ar': 'nameAr',
  'name.en': 'nameEn',
  regionId: 'region',
  cityId: 'city',
}

const BOOLEAN_FIELDS = new Set(['hasStrategicPlan', 'marketingConsent'])
const TRUTHY = new Set(['yes', 'true', true])

const ENUM_GROUPS = {
  entityType: 'entityType',
  developmentSector: 'developmentSector',
  orgSize: 'orgSize',
}

export function useProfileChangeLabels({ regionOptions, cityOptionsFor, regionIdOf }) {
  const { t } = useI18n()

  function labelOf(options, value) {
    return options.find((option) => option.value === String(value))?.label ?? value
  }

  function fieldLabel(field) {
    return t(`entities.fields.${FIELD_LABEL_KEYS[field] ?? field}`)
  }

  function valueLabel(field, value, side) {
    if (value === '' || value === null || value === undefined)
      return t('entities.display.notProvided')
    if (ENUM_GROUPS[field]) return t(`entities.enums.${ENUM_GROUPS[field]}.${value}`)
    if (field === 'regionId') return labelOf(regionOptions.value, value)
    if (field === 'cityId') return labelOf(cityOptionsFor(regionIdOf(side)), value)
    if (BOOLEAN_FIELDS.has(field)) return t(TRUTHY.has(value) ? 'common.yes' : 'common.no')
    return value
  }

  return { fieldLabel, valueLabel }
}
