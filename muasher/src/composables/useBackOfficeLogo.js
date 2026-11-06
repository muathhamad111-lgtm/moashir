import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import logoOnDark from '@/assets/logos/moashir-logo-on-dark.svg'
import { useBrandingStore } from '@/stores/branding'
import { SIDEBAR_LOGO_SURFACE } from '@/constants/branding'

export const DEFAULT_BACK_OFFICE_LOGOS = Object.freeze({ [SIDEBAR_LOGO_SURFACE]: logoOnDark })

export function logoForSurface(logos, surface) {
  return logos?.[surface] || DEFAULT_BACK_OFFICE_LOGOS[surface]
}

export function useBackOfficeLogo() {
  const { logos } = storeToRefs(useBrandingStore())
  const sidebarLogo = computed(() => logoForSurface(logos.value, SIDEBAR_LOGO_SURFACE))
  return { sidebarLogo }
}
