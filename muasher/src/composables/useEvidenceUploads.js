import { onBeforeUnmount, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { evidenceService } from '@/services/evidenceService'
import { useToast } from '@/composables/useToast'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { EVIDENCE_POLL_MS } from '@/constants/accredited'
import { exceedsSize, hasAllowedExtension, hasPendingScan } from '@/utils/accredited/evidenceRules'

export function slotKeyOf(subId, attachmentKey) {
  return `${subId}:${attachmentKey ?? 'general'}`
}

export function useEvidenceUploads({ formState, onRejected }) {
  const { t } = useI18n()
  const toast = useToast()
  const downloads = useSignedDownload()
  const uploading = reactive({})
  const removing = reactive({})
  const errors = reactive({})
  let timer = null

  function assessmentId() {
    return formState.assessment.value.id
  }

  function evidence() {
    return formState.form.value?.evidence ?? []
  }

  function localError(file) {
    const limits = formState.form.value.uploadLimits
    if (!hasAllowedExtension(file, limits.allowedTypes)) return t('accredited.evidence.errors.type', { types: limits.allowedTypes.join(', ') })
    if (exceedsSize(file, limits.maxFileMb)) return t('accredited.evidence.errors.size', { max: limits.maxFileMb })
    return ''
  }

  async function refreshAfterChange() {
    await formState.load({ silent: true })
    watchScans()
  }

  async function upload(subId, attachmentKey, file) {
    const key = slotKeyOf(subId, attachmentKey)
    errors[key] = localError(file)
    if (errors[key] || uploading[key]) return null
    uploading[key] = true
    try {
      const { data } = await evidenceService.upload(assessmentId(), { file, subCriterionId: subId, attachmentKey })
      formState.patch({ evidence: [...evidence(), data] })
      toast.success(t('accredited.evidence.toasts.uploaded', { name: data.fileName }))
      await refreshAfterChange()
      return data
    } catch (error) {
      errors[key] = error?.normalized?.fieldErrors?.file?.[0] ?? error?.normalized?.message ?? ''
      onRejected(error)
      return null
    } finally {
      uploading[key] = false
    }
  }

  async function remove(row) {
    if (removing[row.id]) return false
    removing[row.id] = true
    try {
      await evidenceService.remove(assessmentId(), row.id)
      formState.patch({ evidence: evidence().filter((item) => item.id !== row.id) })
      toast.success(t('accredited.evidence.toasts.removed', { name: row.fileName }))
      await refreshAfterChange()
      return true
    } catch (error) {
      onRejected(error)
      return false
    } finally {
      removing[row.id] = false
    }
  }

  async function replace(row, file) {
    const uploaded = await upload(row.subCriterionId, row.attachmentKey, file)
    if (uploaded) await remove(row)
  }

  function download(row) {
    return downloads.download(row.id, () => evidenceService.requestDownload(assessmentId(), row.id))
  }

  function scanSignature(rows) {
    return rows.map((row) => `${row.id}:${row.scanStatus}`).join('|')
  }

  async function pollScans() {
    const answer = await evidenceService.list(assessmentId()).catch((error) => ({ error }))
    if (answer.data && scanSignature(answer.data) !== scanSignature(evidence())) await formState.load({ silent: true })
    watchScans()
  }

  function watchScans() {
    clearTimeout(timer)
    timer = hasPendingScan(evidence()) ? setTimeout(pollScans, EVIDENCE_POLL_MS) : null
  }

  onBeforeUnmount(() => clearTimeout(timer))

  return { uploading, removing, errors, downloading: downloads.busy, upload, remove, replace, download, watchScans }
}
