import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { entitiesService } from '@/services/entitiesService'
import { useEntityStore } from '@/stores/entity'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { imageDimensionsOf } from '@/utils/imageDimensions'
import { logoDimensionIssue, logoFileIssue } from '@/utils/entityLogoRules'
import { ENTITY_LOGO_LINK_LIFETIME_MS } from '@/constants/entityProfile'
import { fileUploadMessage } from '@/utils/fileUploadError'

export function useEntityLogo() {
  const { t } = useI18n()
  const toast = useToast()
  const { confirm } = useConfirm()
  const entityStore = useEntityStore()
  const busy = ref(false)
  const error = ref('')
  let loadedAt = Date.now()
  let refreshing = false

  const logo = computed(() => entityStore.profile?.logo ?? null)
  const pendingChange = computed(
    () => entityStore.pendingChange?.changes?.find((change) => change.item === 'logo') ?? null,
  )

  async function upload(file) {
    if (!file || busy.value) return
    const issue = logoFileIssue(file) ?? logoDimensionIssue(await imageDimensionsOf(file))
    error.value = issue ? t(`portal.profile.logo.errors.${issue}`) : ''
    if (issue) return
    busy.value = true
    try {
      const { data } = await entitiesService.uploadLogo(file)
      await entityStore.applyProposal(data)
      loadedAt = Date.now()
      toast.success(t('portal.profile.logo.submitted'))
    } catch (failure) {
      error.value = fileUploadMessage(failure, t('errors.unexpected'))
    } finally {
      busy.value = false
    }
  }

  async function remove() {
    if (busy.value) return
    const accepted = await confirm({
      title: t('portal.profile.logo.removeTitle'),
      message: t('portal.profile.logo.removeMessage'),
      variant: 'danger',
      confirmLabel: t('actions.delete'),
    })
    if (!accepted) return
    busy.value = true
    error.value = ''
    try {
      const { data } = await entitiesService.removeLogo()
      await entityStore.applyProposal(data)
    } catch (failure) {
      error.value = fileUploadMessage(failure, t('errors.unexpected'))
    } finally {
      busy.value = false
    }
  }

  async function refreshExpiredLink() {
    if (refreshing || Date.now() - loadedAt < ENTITY_LOGO_LINK_LIFETIME_MS) return
    refreshing = true
    const profile = await entityStore.loadProfile({ force: true }).catch(() => null)
    if (profile) loadedAt = Date.now()
    refreshing = false
  }

  return { logo, pendingChange, busy, error, upload, remove, refreshExpiredLink }
}
