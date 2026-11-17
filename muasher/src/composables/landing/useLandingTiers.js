import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCertificateLevels } from '@/composables/useCertificateLevels'
import { landingTiers, PARTICIPATION } from '@/utils/landing/tiers'
import { localizedText } from '@/utils/localizedText'

export function useLandingTiers() {
  const { t, locale } = useI18n()
  const { ranges, status } = useCertificateLevels()

  function nameOf(tier) {
    return localizedText(tier.label, locale.value) || t(`level.names.${tier.key}`)
  }

  function rangeOf(tier) {
    if (tier.key === PARTICIPATION) return t('landing.levels.rangeBelow', { max: tier.max })
    if (tier.max === null) return t('landing.levels.rangeOpen', { min: tier.min })
    return `${tier.min}–${tier.max}`
  }

  const tiers = computed(() =>
    landingTiers(ranges.value).map((tier) => ({
      ...tier,
      name: nameOf(tier),
      range: rangeOf(tier),
    })),
  )
  const sealTiers = computed(() => tiers.value.filter((tier) => tier.key !== PARTICIPATION))

  return { tiers, sealTiers, status }
}
