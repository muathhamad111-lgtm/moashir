import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import logoOnDark from '@/assets/logos/moashir-logo-on-dark.svg'
import logoOnLight from '@/assets/logos/moashir-logo-on-light.svg'
import { useBrandingStore } from '@/stores/branding'
import {
  BRAND_SURFACES,
  FAVICON_SURFACE,
  SIDEBAR_LOGO_SURFACE,
  SITE_LOGO_SURFACE,
} from '@/constants/branding'

const DEFAULT_FAVICON = '/android-chrome-512x512.png'

export const DEFAULT_BACK_OFFICE_LOGOS = Object.freeze({
  [SIDEBAR_LOGO_SURFACE]: logoOnDark,
  [FAVICON_SURFACE]: DEFAULT_FAVICON,
  [SITE_LOGO_SURFACE]: logoOnLight,
})

export function logoForSurface(logos, surface) {
  return logos?.[surface] || DEFAULT_BACK_OFFICE_LOGOS[surface]
}

// The logo drawn for light text on a dark fill, or the one for a light background.
export function useBackOfficeLogo() {
  const { logos, scheme } = storeToRefs(useBrandingStore())
  const logoOn = (text) =>
    text === BRAND_SURFACES.paper
      ? logoForSurface(logos.value, SIDEBAR_LOGO_SURFACE)
      : logoForSurface(logos.value, SITE_LOGO_SURFACE)
  const sidebarLogo = computed(() => logoOn(scheme.value.onRail))
  const topBarLogo = computed(() => logoOn(scheme.value.onSecondary))
  return { sidebarLogo, topBarLogo }
}
