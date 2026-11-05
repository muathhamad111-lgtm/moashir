import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { certificatesService } from '@/services/certificatesService'
import { usePermissions } from '@/composables/usePermissions'
import { useSuperAdminReason } from '@/composables/useSuperAdminReason'
import { useToast } from '@/composables/useToast'
import { PERMISSIONS } from '@/constants/permissionKeys'

const CURRENT_STATUSES = ['issued', 'suspended']
const REVOCABLE = ['issued', 'suspended']
const REISSUABLE = ['issued', 'suspended', 'revoked']

export function useRequestCertificates(requestId) {
  const { t } = useI18n()
  const toast = useToast()
  const { can } = usePermissions()
  const { prompt: reasonPrompt, reasonExtra } = useSuperAdminReason()
  const certificates = ref([])
  const status = ref('idle')
  const errorMessage = ref('')
  const issuing = ref(false)

  const canManage = computed(() => can(PERMISSIONS.CERTIFICATES_MANAGE))
  const canIssue = computed(() => can(PERMISSIONS.CERTIFICATES_ISSUE_ACCREDITED))
  const current = computed(
    () => certificates.value.find((item) => CURRENT_STATUSES.includes(item.status)) ?? null,
  )
  const history = computed(() => certificates.value.filter((item) => item !== current.value))

  async function load() {
    if (!canManage.value) return
    status.value = 'loading'
    errorMessage.value = ''
    try {
      certificates.value = (await certificatesService.listForRequest(requestId.value)).data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  function remember(certificate) {
    certificates.value = [
      certificate,
      ...certificates.value.filter((item) => item.id !== certificate.id),
    ]
  }

  async function issue() {
    const extra = await reasonExtra()
    if (!extra || issuing.value) return false
    issuing.value = true
    try {
      remember((await certificatesService.issueForRequest(requestId.value, extra)).data)
      toast.success(t('requests.certificate.toasts.issued'))
      return true
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
      return false
    } finally {
      issuing.value = false
    }
  }

  async function revoke(certificate, reason) {
    await certificatesService.revoke(certificate.id, reason)
    toast.success(t('requests.certificate.toasts.revoked'))
    await load()
  }

  async function reissue(certificate, reason) {
    await certificatesService.reissue(certificate.id, reason)
    toast.success(t('requests.certificate.toasts.reissued'))
    await load()
  }

  function canRevoke(certificate) {
    return canManage.value && REVOCABLE.includes(certificate?.status)
  }

  function canReissue(certificate) {
    return canManage.value && REISSUABLE.includes(certificate?.status)
  }

  return {
    certificates,
    current,
    history,
    status,
    errorMessage,
    issuing,
    canManage,
    canIssue,
    reasonPrompt,
    load,
    issue,
    revoke,
    reissue,
    canRevoke,
    canReissue,
  }
}
