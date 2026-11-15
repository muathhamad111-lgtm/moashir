import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { accountAvatarService } from '@/services/accountAvatarService'
import { useAuthStore } from '@/stores/auth'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { imageDimensionsOf } from '@/utils/imageDimensions'
import { imageDimensionIssue, imageFileIssue } from '@/utils/imageUploadRules'
import { fileUploadMessage } from '@/utils/fileUploadError'
import { ACCOUNT_AVATAR_RULES, AVATAR_SOURCES } from '@/constants/accountAvatar'

export function useProfilePhoto() {
  const { t } = useI18n()
  const toast = useToast()
  const { confirm } = useConfirm()
  const auth = useAuthStore()
  const busy = ref(false)
  const error = ref('')

  const photo = computed(() => {
    const avatar = auth.user?.avatar ?? null
    return avatar?.source === AVATAR_SOURCES.photo ? avatar : null
  })

  async function localIssue(file) {
    const issue =
      imageFileIssue(file, ACCOUNT_AVATAR_RULES) ??
      imageDimensionIssue(await imageDimensionsOf(file), ACCOUNT_AVATAR_RULES)
    return issue ? t(`account.avatar.errors.${issue}`) : ''
  }

  async function run(request, successKey) {
    busy.value = true
    try {
      const { data } = await request()
      auth.applyAvatar(data.avatar)
      toast.success(t(successKey))
    } catch (failure) {
      error.value = fileUploadMessage(failure, t('errors.unexpected'))
    } finally {
      busy.value = false
    }
  }

  async function upload(file) {
    if (!file || busy.value) return
    error.value = await localIssue(file)
    if (error.value) return
    await run(() => accountAvatarService.upload(file), 'account.avatar.uploaded')
  }

  async function remove() {
    if (busy.value) return
    const accepted = await confirm({
      title: t('account.avatar.removeTitle'),
      message: t('account.avatar.removeMessage'),
      variant: 'danger',
      confirmLabel: t('actions.delete'),
    })
    if (!accepted) return
    error.value = ''
    await run(() => accountAvatarService.remove(), 'account.avatar.removed')
  }

  return { photo, busy, error, upload, remove }
}
