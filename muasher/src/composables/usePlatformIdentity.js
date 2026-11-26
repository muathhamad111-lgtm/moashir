import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePublicHome } from '@/composables/useHomeContent'

export function usePlatformIdentity() {
  const { t } = useI18n()
  const { siteSettings, localized } = usePublicHome({ auto: false })

  const managedName = computed(() => localized(siteSettings.value?.platformName))
  const platformName = computed(() => managedName.value || t('app.name'))
  const siteName = computed(() => managedName.value || t('meta.siteName'))
  const platformDescription = computed(
    () => localized(siteSettings.value?.platformDescription) || t('footer.description'),
  )

  return { platformName, siteName, platformDescription }
}
