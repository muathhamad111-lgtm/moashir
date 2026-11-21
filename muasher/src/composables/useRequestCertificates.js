import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { certificatesService } from '@/services/certificatesService'
import { usePermissions } from '@/composables/usePermissions'
import { useSuperAdminReason } from '@/composables/useSuperAdminReason'
import { useToast } from '@/composables/useToast'
import { success } from '@/composables/useSuccess'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ISSUANCE_MODES } from '@/constants/certificates'

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
  const issuanceMode = ref(null)

  const canManage = computed(() => can(PERMISSIONS.CERTIFICATES_MANAGE))
  const canIssue = computed(() => can(PERMISSIONS.CERTIFICATES_ISSUE_ACCREDITED))
  const current = computed(
    () => certificates.value.find((item) => CURRENT_STATUSES.includes(item.status)) ?? null,
  )
  const history = computed(() => certificates.value.filter((item) => item !== current.value))
  const issuesAutomatically = computed(() => issuanceMode.value === ISSUANCE_MODES.AUTOMATIC)

  // Manual issuance waits for the assessor's button; automatic issuance never shows it.
  function issueStepOf(completed) {
    if (!completed || status.value !== 'success' || current.value) return null
    if (issuesAutomatically.value) return 'pending'
    return canIssue.value ? 'issue' : null
  }

  // Managers read the whole history; the assigned assessor reads only the current state.
  async function fetchCertificates() {
    if (canManage.value) {
      const envelope = await certificatesService.listForRequest(requestId.value)
      return { list: envelope.data, mode: envelope.issuanceMode }
    }
    const { data } = await certificatesService.requestState(requestId.value)
    return { list: data.certificate ? [data.certificate] : [], mode: data.issuanceMode }
  }

  async function load() {
    if (!canManage.value && !canIssue.value) return
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { list, mode } = await fetchCertificates()
      certificates.value = list
      issuanceMode.value = mode
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
      const { data } = await certificatesService.issueForRequest(requestId.value, extra)
      remember(data)
      success.show({ title: t('requests.certificate.toasts.issued'), message: data?.number ?? '' })
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
    success.show({ title: t('requests.certificate.toasts.revoked'), message: certificate.number })
    await load()
  }

  async function reissue(certificate, reason) {
    const { data } = await certificatesService.reissue(certificate.id, reason)
    success.show({ title: t('requests.certificate.toasts.reissued'), message: data?.number ?? '' })
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
    issuesAutomatically,
    issueStepOf,
    reasonPrompt,
    load,
    issue,
    revoke,
    reissue,
    canRevoke,
    canReissue,
  }
}
