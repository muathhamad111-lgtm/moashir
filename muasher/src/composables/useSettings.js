import { useI18n } from 'vue-i18n'

export const EMAIL_SEGMENTS = [
  'self-only-not-accredited',
  'registered-advanced-not-qualified',
  'qualified',
  'last-was-accredited',
  'all',
  'individual',
]

export function useSettings() {
  const { t, locale } = useI18n()

  function localized(content) {
    if (content === null || content === undefined) return ''
    if (typeof content === 'string') return content
    return content[locale.value] || content.ar || content.en || ''
  }

  function segmentLabel(segment) {
    return t(`settings.email.segments.${segment}`)
  }

  return {
    t,
    locale,
    localized,
    segmentLabel,
  }
}
