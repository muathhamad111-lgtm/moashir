import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { localizedText } from '@/utils/localizedText'
import { AVATAR_FALLBACK_ICONS, AVATAR_SOURCES } from '@/constants/accountAvatar'

export function useAccountAvatar() {
  const { t, locale } = useI18n()
  const auth = useAuthStore()
  const failedUrl = ref('')

  const avatar = computed(() => auth.user?.avatar ?? null)
  const isEntityAccount = computed(() => auth.isEntity || Boolean(auth.user?.entityId))
  const isLogo = computed(() => avatar.value?.source === AVATAR_SOURCES.entityLogo)
  const imageUrl = computed(() => {
    const url = avatar.value?.url ?? ''
    return url && url !== failedUrl.value ? url : ''
  })
  const fallbackIcon = computed(() =>
    isEntityAccount.value ? AVATAR_FALLBACK_ICONS.entity : AVATAR_FALLBACK_ICONS.person,
  )
  const altText = computed(() => {
    const userName = localizedText(auth.user?.name, locale.value) || t('nav.userFallback')
    if (!isEntityAccount.value) return t('account.avatar.photoAlt', { name: userName })
    const entityName = localizedText(auth.user?.entity?.name, locale.value) || userName
    return t('account.avatar.logoAlt', { name: entityName })
  })

  function markFailed() {
    failedUrl.value = avatar.value?.url ?? ''
  }

  return { imageUrl, isLogo, fallbackIcon, altText, markFailed }
}
