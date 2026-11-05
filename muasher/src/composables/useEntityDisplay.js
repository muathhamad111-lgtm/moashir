import { useI18n } from 'vue-i18n'
import { formatDate } from '@/utils/datetime'
import { formatGovernanceGrade } from '@/utils/governanceGrade'
import { localizedText } from '@/utils/localizedText'
import { withLatinNumerals } from '@/utils/numerals'

const LTR_FIELDS = new Set(['reference', 'officialEmail', 'phone', 'nameEn', 'governanceGrade'])
const FREE_TEXT_FIELDS = new Set(['deactivationReason', 'deletionReason'])

export function useEntityDisplay() {
  const { t, locale } = useI18n()

  const yesNo = (value) => t(value ? 'common.yes' : 'common.no')
  const count = (value) =>
    value === null || value === undefined
      ? ''
      : new Intl.NumberFormat(withLatinNumerals(locale.value)).format(value)
  const date = (value) => (value ? formatDate(value, locale.value, { dateStyle: 'medium' }) : '')
  const dateTime = (value) =>
    value ? formatDate(value, locale.value, { dateStyle: 'medium', timeStyle: 'short' }) : ''
  const enumLabel = (group, value) => (value ? t(`entities.enums.${group}.${value}`) : '')

  const FORMATTERS = {
    reference: (entity) => entity.reference,
    nameAr: (entity) => entity.name?.ar,
    nameEn: (entity) => entity.name?.en,
    authorizedPersonName: (entity) => entity.authorizedPersonName,
    officialEmail: (entity) => entity.officialEmail,
    phone: (entity) => entity.phone,
    entityType: (entity) => enumLabel('entityType', entity.entityType),
    developmentSector: (entity) => enumLabel('developmentSector', entity.developmentSector),
    orgSize: (entity) => enumLabel('orgSize', entity.orgSize),
    foundingYear: (entity) => (entity.foundingYear ? String(entity.foundingYear) : ''),
    region: (entity) => localizedText(entity.region?.name, locale.value),
    city: (entity) => localizedText(entity.city?.name, locale.value),
    governanceGrade: (entity) => formatGovernanceGrade(entity.governanceGrade),
    employeesCount: (entity) => count(entity.employeesCount),
    annualBeneficiariesCount: (entity) => count(entity.annualBeneficiariesCount),
    hasStrategicPlan: (entity) => yesNo(entity.hasStrategicPlan),
    marketingConsent: (entity) => yesNo(entity.marketingConsent),
    createdAt: (entity) => date(entity.createdAt),
    verifiedAt: (entity) => date(entity.verifiedAt),
    deactivatedAt: (entity) => dateTime(entity.deactivatedAt),
    deactivationReason: (entity) => entity.deactivationReason,
    verificationRemindedAt: (entity) => dateTime(entity.verificationRemindedAt),
    deletedAt: (entity) => dateTime(entity.deletedAt),
    deletionReason: (entity) => entity.deletionReason,
    restoredAt: (entity) => dateTime(entity.restoredAt),
  }

  function rowsOf(entity, fields, { skipEmpty = false } = {}) {
    return fields
      .map((key) => ({ key, value: FORMATTERS[key](entity) }))
      .filter((row) => !skipEmpty || Boolean(row.value))
      .map(({ key, value }) => ({
        key,
        label: t(`entities.fields.${key}`),
        value: value || t('entities.display.notProvided'),
        ltr: LTR_FIELDS.has(key),
        free: FREE_TEXT_FIELDS.has(key),
      }))
  }

  return {
    rowsOf,
    date,
    dateTime,
    enumLabel,
    localized: (content) => localizedText(content, locale.value),
  }
}
