import { useI18n } from 'vue-i18n'
import { useCriteria } from '@/composables/useCriteria'
import { formatDate } from '@/utils/datetime'

const EMPTY = '—'

// How a criteria version and a publication date read in the versions tab and the "What changed" page.
export function useCriteriaVersionText() {
  const { t, locale } = useI18n()
  const { localized } = useCriteria()

  function versionName(version) {
    if (!version) return EMPTY
    const label = localized(version.label)
    return label
      ? t('criteria.versions.nameWithLabel', { number: version.versionNumber, label })
      : t('criteria.versions.name', { number: version.versionNumber })
  }

  // Without the label: for the places that name two versions or sit in a breadcrumb.
  function versionNumber(version) {
    return version ? t('criteria.versions.name', { number: version.versionNumber }) : EMPTY
  }

  function dateOf(value) {
    if (!value) return EMPTY
    return formatDate(value, locale.value, { dateStyle: 'medium', timeZone: 'Asia/Riyadh' })
  }

  return { versionName, versionNumber, dateOf }
}
