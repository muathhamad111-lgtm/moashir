import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import logoOnDark from '@/assets/logos/logo-lockup-green.png'
import logoOnLight from '@/assets/logos/logo-lockup-dark.png'
import { useBrandingStore } from '@/stores/branding'
import { useTheme } from '@/composables/useTheme'
import { THEMES } from '@/constants/themes'

export const DEFAULT_BACK_OFFICE_LOGOS = Object.freeze({ dark: logoOnDark, light: logoOnLight })

export function logoForSurface(logos, surface) {
  return logos?.[surface] || DEFAULT_BACK_OFFICE_LOGOS[surface]
}

export function useBackOfficeLogo() {
  const { logos } = storeToRefs(useBrandingStore())
  const { theme } = useTheme()
  const surface = computed(() => (theme.value === THEMES.light ? 'light' : 'dark'))
  const sidebarLogo = computed(() => logoForSurface(logos.value, surface.value))
  return { sidebarLogo, surface }
}
