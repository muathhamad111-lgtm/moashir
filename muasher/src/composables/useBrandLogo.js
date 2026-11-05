import { computed } from 'vue'
import logoOnDark from '@/assets/logos/moashir-logo-on-dark.svg'
import logoOnLight from '@/assets/logos/moashir-logo-on-light.svg'
import { useTheme } from '@/composables/useTheme'
import { THEMES } from '@/constants/themes'

export function useBrandLogo() {
  const { theme } = useTheme()

  const themedLogo = computed(() => (theme.value === THEMES.light ? logoOnLight : logoOnDark))

  return { themedLogo, logoOnDark, logoOnLight }
}
