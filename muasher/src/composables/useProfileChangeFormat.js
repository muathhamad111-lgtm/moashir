import { useI18n } from 'vue-i18n'
import { formatDate } from '@/utils/datetime'
import { formatGovernanceGrade } from '@/utils/governanceGrade'
import { localizedText } from '@/utils/localizedText'
import { withLatinNumerals } from '@/utils/numerals'

const ENUM_GROUPS = Object.freeze({ entity_type: 'entityType', development_sector: 'developmentSector', org_size: 'orgSize' })
const PLACE_FIELDS = new Set(['region_id', 'city_id'])
const COUNT_FIELDS = new Set(['employees_count', 'annual_beneficiaries_count'])
const STAFF = 'staff'

export function useProfileChangeFormat() {
  const { t, te, locale } = useI18n()

  function nameValue(value) {
    return [value.ar, value.en].filter(Boolean).join(' / ')
  }

  function enumValue(group, value) {
    const key = `entities.enums.${group}.${value}`
    return te(key) ? t(key) : String(value)
  }

  const FORMATTERS = {
    name: nameValue,
    governance_grade: (value) => formatGovernanceGrade(value),
    has_strategic_plan: (value) => t(value ? 'common.yes' : 'common.no'),
  }

  function valueLabel(field, value) {
    if (value === null || value === undefined || value === '') return t('entities.display.notProvided')
    if (FORMATTERS[field]) return FORMATTERS[field](value)
    if (ENUM_GROUPS[field]) return enumValue(ENUM_GROUPS[field], value)
    if (PLACE_FIELDS.has(field)) return localizedText(value.name, locale.value)
    if (COUNT_FIELDS.has(field)) return new Intl.NumberFormat(withLatinNumerals(locale.value)).format(value)
    return String(value)
  }

  function fieldLabel(change) {
    return localizedText(change.fieldLabel, locale.value) || change.field
  }

  function actorLabel(change, { showStaffIdentity }) {
    const category = t(`entities.history.actors.${change.changedBy}`)
    const staff = change.changedByStaff
    if (!showStaffIdentity || change.changedBy !== STAFF || !staff?.name) return category
    const role = staff.roles?.[0]?.label
    return role ? t('entities.history.staffWithRole', { name: staff.name, role: localizedText(role, locale.value) }) : staff.name
  }

  function changedAt(change) {
    return formatDate(change.changedAt, locale.value, { dateStyle: 'medium', timeStyle: 'short' })
  }

  return { valueLabel, fieldLabel, actorLabel, changedAt }
}
