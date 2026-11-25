import { computed, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { filesService } from '@/services/filesService'
import { downloadBlob } from '@/utils/download'
import { financeErrorMessage } from '@/utils/payments/financeErrors'
import { PROOF_ZOOM } from '@/constants/bankTransfer'
import { FILE_INTENTS } from '@/constants/files'
import { useAccountCompletionStore } from '@/stores/accountCompletion'

export const PREVIEW_STATES = Object.freeze({
  IDLE: 'idle',
  LOADING: 'loading',
  READY: 'ready',
  ERROR: 'error',
})

function objectUrlOf(blob) {
  return typeof URL.createObjectURL === 'function' ? URL.createObjectURL(blob) : ''
}

function revoke(url) {
  if (url && typeof URL.revokeObjectURL === 'function') URL.revokeObjectURL(url)
}

function useZoom() {
  const zoom = ref(PROOF_ZOOM.DEFAULT)
  return {
    zoom,
    canZoomIn: computed(() => zoom.value < PROOF_ZOOM.MAX),
    canZoomOut: computed(() => zoom.value > PROOF_ZOOM.MIN),
    zoomIn: () => (zoom.value = Math.min(PROOF_ZOOM.MAX, zoom.value + PROOF_ZOOM.STEP)),
    zoomOut: () => (zoom.value = Math.max(PROOF_ZOOM.MIN, zoom.value - PROOF_ZOOM.STEP)),
  }
}

function useLoadedFile() {
  const objectUrl = ref('')
  const file = ref(null)

  function keep(loaded) {
    file.value = loaded
    revoke(objectUrl.value)
    objectUrl.value = objectUrlOf(loaded.blob)
  }

  onBeforeUnmount(() => revoke(objectUrl.value))
  return { objectUrl, file, keep }
}

/** Fetches a signed file for an inline preview, and downloads it, from the link `requestLink` issues. */
export function useDocumentPreview(requestLink) {
  const i18n = useI18n()
  const toast = useToast()
  const completion = useAccountCompletionStore()
  const state = ref(PREVIEW_STATES.IDLE)
  const downloading = ref(false)
  const loaded = useLoadedFile()

  async function fetchFile(intent) {
    const { data: link } = await requestLink()
    return filesService.fetchSignedFile(link, { intent })
  }

  async function load() {
    if (state.value === PREVIEW_STATES.LOADING) return
    state.value = PREVIEW_STATES.LOADING
    try {
      loaded.keep(await fetchFile(FILE_INTENTS.VIEW))
      state.value = PREVIEW_STATES.READY
    } catch (error) {
      completion.refuseFileAccess(error)
      state.value = PREVIEW_STATES.ERROR
    }
  }

  async function download() {
    if (downloading.value) return
    downloading.value = true
    try {
      const file = loaded.file.value ?? (await fetchFile(FILE_INTENTS.DOWNLOAD))
      downloadBlob(file.blob, file.fileName)
    } catch (error) {
      if (!completion.refuseFileAccess(error))
        toast.error(financeErrorMessage(i18n, error, 'finance.downloads.failed'))
    } finally {
      downloading.value = false
    }
  }

  return { state, objectUrl: loaded.objectUrl, downloading, load, download, ...useZoom() }
}
