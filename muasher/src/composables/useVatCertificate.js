import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { entitiesService } from '@/services/entitiesService'
import { useEntityStore } from '@/stores/entity'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { fileUploadMessage } from '@/utils/fileUploadError'
import { vatCertificateIssue } from '@/utils/entityProfile/vatCertificateRules'
import { VAT_CERTIFICATE_RULES } from '@/constants/entityProfile'

const DOWNLOAD_KEY = 'vat-certificate'
const MESSAGES = 'portal.profile.billing.certificate'

function issueMessage(t, issue) {
  return issue ? t(`${MESSAGES}.errors.${issue}`, { max: VAT_CERTIFICATE_RULES.maxMb }) : ''
}

function removalQuestion(t) {
  return {
    title: t(`${MESSAGES}.removeTitle`),
    message: t(`${MESSAGES}.removeMessage`),
    variant: 'danger',
    confirmLabel: t('actions.delete'),
  }
}

export function useVatCertificate() {
  const { t } = useI18n()
  const toast = useToast()
  const { confirm } = useConfirm()
  const entityStore = useEntityStore()
  const downloads = useSignedDownload()
  const busy = ref(false)
  const error = ref('')

  const certificate = computed(() => entityStore.profile?.vatCertificate ?? null)
  const canUpload = computed(() => entityStore.profile?.vatRegistered === true)
  const downloading = computed(() => Boolean(downloads.busy[DOWNLOAD_KEY]))

  async function run(action, successKey) {
    busy.value = true
    error.value = ''
    try {
      const { data } = await action()
      entityStore.applyProfile(data)
      if (successKey) toast.success(t(successKey))
    } catch (failure) {
      error.value = fileUploadMessage(failure, t('errors.unexpected'))
    } finally {
      busy.value = false
    }
  }

  async function upload(file) {
    if (busy.value) return
    const issue = vatCertificateIssue(file)
    error.value = issueMessage(t, issue)
    if (!issue) await run(() => entitiesService.uploadVatCertificate(file), `${MESSAGES}.uploaded`)
  }

  async function remove() {
    if (busy.value) return
    if (await confirm(removalQuestion(t))) {
      await run(() => entitiesService.removeVatCertificate(), null)
    }
  }

  function download() {
    return downloads.download(DOWNLOAD_KEY, () => entitiesService.requestVatCertificateDownload())
  }

  return { certificate, canUpload, busy, error, downloading, upload, remove, download }
}
