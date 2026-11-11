import { useI18n } from 'vue-i18n'
import { formatRiyadhLongDate } from '@/utils/datetime'
import { formatGovernanceGrade } from '@/utils/governanceGrade'
import { localizedText } from '@/utils/localizedText'
import { withLatinNumerals } from '@/utils/numerals'
import { PROFILE_LTR_FIELDS } from '@/constants/entityProfile'

const LOCAL_PHONE = /^\+966(5\d)(\d{3})(\d{4})$/

export function formatLocalPhone(phone) {
  const match = LOCAL_PHONE.exec(String(phone ?? ''))
  return match ? `0${match[1]} ${match[2]} ${match[3]}` : String(phone ?? '')
}

function present(value) {
  return value !== null && value !== undefined && value !== ''
}

export function useProfileFieldDisplay() {
  const { t, locale } = useI18n()

  const count = (value) =>
    present(value) ? new Intl.NumberFormat(withLatinNumerals(locale.value)).format(value) : ''
  const enumLabel = (group, value) => (value ? t(`entities.enums.${group}.${value}`) : '')
  const yesNo = (value) => (present(value) ? t(value ? 'common.yes' : 'common.no') : '')

  const READERS = {
    'name.ar': (profile) => profile.name?.ar,
    'name.en': (profile) => profile.name?.en,
    unifiedNumber: (profile) => profile.unifiedNumber,
    licenseNumber: (profile) => profile.licenseNumber,
    foundedOn: (profile) => formatRiyadhLongDate(profile.foundedOn, locale.value),
    entityType: (profile) => enumLabel('entityType', profile.entityType),
    developmentSector: (profile) => enumLabel('developmentSector', profile.developmentSector),
    orgSize: (profile) => enumLabel('orgSize', profile.orgSize),
    regionId: (profile) => localizedText(profile.region?.name, locale.value),
    cityId: (profile) => localizedText(profile.city?.name, locale.value),
    governanceGrade: (profile) => formatGovernanceGrade(profile.governanceGrade),
    employeesCount: (profile) => count(profile.employeesCount),
    annualBeneficiariesCount: (profile) => count(profile.annualBeneficiariesCount),
    hasStrategicPlan: (profile) => yesNo(profile.hasStrategicPlan),
    officialEmail: (profile) => profile.officialEmail,
    authorizedPersonName: (profile) => profile.authorizedPersonName,
    phone: (profile) => (profile.phone ? formatLocalPhone(profile.phone) : ''),
    websiteUrl: (profile) => profile.websiteUrl,
    marketingConsent: (profile) =>
      t(`portal.profile.values.${profile.marketingConsent ? 'subscribed' : 'notSubscribed'}`),
  }

  function valueOf(profile, name) {
    const read = READERS[name]
    const value = read ? read(profile ?? {}) : ''
    return present(value) ? String(value) : ''
  }

  function isLtr(name) {
    return PROFILE_LTR_FIELDS.includes(name)
  }

  return { valueOf, isLtr }
}
