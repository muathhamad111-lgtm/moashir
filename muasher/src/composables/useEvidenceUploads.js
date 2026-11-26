import { onBeforeUnmount, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { evidenceService } from '@/services/evidenceService'
import { useToast } from '@/composables/useToast'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { EVIDENCE_POLL_MS } from '@/constants/accredited'
import {
  EVIDENCE_LEVELS,
  exceedsSize,
  hasAllowedExtension,
  hasPendingScan,
  ownerOfEvidence,
  slotTypesOf,
} from '@/utils/accredited/evidenceRules'

// owner: { level: 'main' | 'sub', id }; a null key is the sub's additional evidence.
export function slotKeyOf(owner, attachmentKey) {
  return `${owner.level}:${owner.id}:${attachmentKey ?? 'general'}`
}

function targetOf(owner) {
  return owner.level === EVIDENCE_LEVELS.MAIN
    ? { mainCriterionId: owner.id }
    : { subCriterionId: owner.id }
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

  function localError(file, slot) {
    const limits = formState.form.value.uploadLimits
    const types = slotTypesOf(slot, limits)
    if (!hasAllowedExtension(file, types))
      return t('accredited.evidence.errors.type', { types: types.join(', ') })
    if (exceedsSize(file, limits.maxFileMb))
      return t('accredited.evidence.errors.size', { max: limits.maxFileMb })
    return ''
  }

  async function refreshAfterChange() {
    await formState.load({ silent: true })
    watchScans()
  }

  // slot: the attachment as the criteria tree gives it, or null for the sub's additional evidence.
  async function upload(owner, slot, file) {
    const key = slotKeyOf(owner, slot?.key)
    errors[key] = localError(file, slot)
    if (errors[key] || uploading[key]) return null
    uploading[key] = true
    try {
      const { data } = await evidenceService.upload(assessmentId(), {
        file,
        ...targetOf(owner),
        attachmentKey: slot?.key ?? null,
      })
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

  async function replace(row, slot, file) {
    const uploaded = await upload(ownerOfEvidence(row), slot, file)
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
    if (answer.data && scanSignature(answer.data) !== scanSignature(evidence()))
      await formState.load({ silent: true })
    watchScans()
  }

  function watchScans() {
    clearTimeout(timer)
    timer = hasPendingScan(evidence()) ? setTimeout(pollScans, EVIDENCE_POLL_MS) : null
  }

  onBeforeUnmount(() => clearTimeout(timer))

  return {
    uploading,
    removing,
    errors,
    downloading: downloads.busy,
    upload,
    remove,
    replace,
    download,
    watchScans,
  }
}
