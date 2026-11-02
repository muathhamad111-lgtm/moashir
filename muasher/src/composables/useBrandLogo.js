import { computed } from 'vue'
import logoGreen from '@/assets/logos/logo-lockup-green.png'
import logoDark from '@/assets/logos/logo-lockup-dark.png'
import { useTheme } from '@/composables/useTheme'
import { THEMES } from '@/constants/themes'

export function useBrandLogo() {
  const { theme } = useTheme()

  const themedLogo = computed(() =>
    theme.value === THEMES.light ? logoDark : logoGreen,
  )

  return { themedLogo, logoOnDark: logoGreen, logoOnLight: logoDark }
}
