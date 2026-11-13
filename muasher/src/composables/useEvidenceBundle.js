import { onBeforeUnmount, ref, shallowRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { evidenceService } from '@/services/evidenceService'
import { useToast } from '@/composables/useToast'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { BUNDLE_POLL_MS, BUNDLE_STATUSES } from '@/constants/accredited'
import { ROUTE_NAMES } from '@/constants/routeNames'

const NOT_FOUND = 404

export function useEvidenceBundleRequest(assessmentId) {
  const { t } = useI18n()
  const router = useRouter()
  const toast = useToast()
  const downloads = useSignedDownload()
  const requesting = ref(false)

  async function requestBundle() {
    if (requesting.value) return
    requesting.value = true
    try {
      const { data } = await evidenceService.requestBundle(assessmentId.value)
      if (data.status === BUNDLE_STATUSES.READY) {
        await downloads.download(data.id, () =>
          evidenceService.requestBundleDownload(assessmentId.value, data.id),
        )
        return
      }
      toast.info(t('requests.bundle.preparing'))
      router.push({
        name: ROUTE_NAMES.REQUESTS_EVIDENCE_BUNDLE,
        params: { id: assessmentId.value, bundle: data.id },
      })
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
    } finally {
      requesting.value = false
    }
  }

  return { requesting, requestBundle }
}

export function useEvidenceBundleStatus(assessmentId, bundleId) {
  const downloads = useSignedDownload()
  const status = ref('loading')
  const errorMessage = ref('')
  const bundle = shallowRef(null)
  let timer = null

  function schedule() {
    clearTimeout(timer)
    timer =
      bundle.value?.status === BUNDLE_STATUSES.PREPARING
        ? setTimeout(() => load({ silent: true }), BUNDLE_POLL_MS)
        : null
  }

  async function load({ silent = false } = {}) {
    if (!silent) status.value = 'loading'
    try {
      const { data } = await evidenceService.getBundle(assessmentId.value, bundleId.value)
      bundle.value = data
      status.value = 'ready'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = error?.normalized?.status === NOT_FOUND ? 'missing' : 'error'
    } finally {
      schedule()
    }
  }

  function download() {
    return downloads.download(bundleId.value, () =>
      evidenceService.requestBundleDownload(assessmentId.value, bundleId.value),
    )
  }

  onBeforeUnmount(() => clearTimeout(timer))

  return { status, errorMessage, bundle, downloading: downloads.busy, load, download }
}
