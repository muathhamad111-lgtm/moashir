import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

export const CERTIFICATE_KINDS = ['accredited', 'advanced-winner', 'advanced-qualified']
export const CERTIFICATE_FONT_TYPES = ['iwanzaza', 'montserrat', 'tajawal', 'cairo']
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

  function toOptions(values, prefix) {
    return values.map((value) => ({ value, label: t(`${prefix}.${value}`) }))
  }

  const certificateFontOptions = computed(() =>
    toOptions(CERTIFICATE_FONT_TYPES, 'settings.certificates.fonts'),
  )

  function certificateKindLabel(kind) {
    return t(`settings.certificates.kinds.${kind}`)
  }

  function segmentLabel(segment) {
    return t(`settings.email.segments.${segment}`)
  }

  return {
    t,
    locale,
    localized,
    certificateFontOptions,
    certificateKindLabel,
    segmentLabel,
  }
}
