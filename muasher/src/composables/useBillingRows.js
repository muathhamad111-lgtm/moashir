import { useI18n } from 'vue-i18n'
import { fieldLabelKey } from '@/utils/entityProfile/sections'
import { PROFILE_LTR_FIELDS } from '@/constants/entityProfile'

const ADDRESS_FIELDS = Object.freeze([
  'addressBuildingNumber',
  'addressStreet',
  'addressDistrict',
  'addressCity',
  'addressPostalCode',
  'addressAdditionalNumber',
  'addressShortCode',
])

export function useBillingRows() {
  const { t } = useI18n()

  function vatAnswer(billing) {
    if (billing.vatRegistered === null || billing.vatRegistered === undefined) return ''
    return t(billing.vatRegistered ? 'common.yes' : 'common.no')
  }

  function row(key, value) {
    return {
      key,
      label: t(fieldLabelKey(key)),
      value: value || t('entities.display.notProvided'),
      ltr: Boolean(value) && PROFILE_LTR_FIELDS.includes(key),
      free: false,
      href: null,
    }
  }

  function vatRows(billing) {
    if (billing.vatRegistered !== true) return []
    return [
      row('vatNumber', billing.vatNumber),
      row('vatCertificate', billing.vatCertificate?.name ?? ''),
    ]
  }

  // The Saudi national address (building number, street, district, city, postal code, extras).
  function addressRowsOf(billing) {
    return billing ? ADDRESS_FIELDS.map((key) => row(key, billing[key])) : []
  }

  function taxRowsOf(billing) {
    return billing ? [row('vatRegistered', vatAnswer(billing)), ...vatRows(billing)] : []
  }

  function rowsOf(billing) {
    return [...addressRowsOf(billing), ...taxRowsOf(billing)]
  }

  return { rowsOf, addressRowsOf, taxRowsOf }
}
