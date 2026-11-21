import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { usePublicHomeStore } from '@/stores/publicHome'
import { useBrandingStore } from '@/stores/branding'
import { FAVICON_SURFACE } from '@/constants/branding'
import { contentImageUrl } from '@/utils/contentImage'

export function useSiteBranding() {
  const { siteSettings } = storeToRefs(usePublicHomeStore())
  const { logos } = storeToRefs(useBrandingStore())

  const siteLogo = computed(() => contentImageUrl(siteSettings.value?.branding?.siteLogo))
  const favicon = computed(
    () => logos.value?.[FAVICON_SURFACE] || contentImageUrl(siteSettings.value?.branding?.favicon),
  )

  return { siteLogo, favicon }
}
