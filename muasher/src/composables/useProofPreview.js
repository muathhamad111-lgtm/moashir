import { computed, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { filesService } from '@/services/filesService'
import { downloadBlob } from '@/utils/download'
import { financeErrorMessage } from '@/utils/payments/financeErrors'
import { PROOF_ZOOM } from '@/constants/bankTransfer'

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

export function useProofPreview(requestLink) {
  const i18n = useI18n()
  const toast = useToast()
  const state = ref(PREVIEW_STATES.IDLE)
  const objectUrl = ref('')
  const fileName = ref('')
  const zoom = ref(PROOF_ZOOM.DEFAULT)
  const downloading = ref(false)
  let blob = null

  const canZoomIn = computed(() => zoom.value < PROOF_ZOOM.MAX)
  const canZoomOut = computed(() => zoom.value > PROOF_ZOOM.MIN)

  async function fetchFile() {
    const { data: link } = await requestLink()
    return filesService.fetchSignedFile(link)
  }

  async function load() {
    if (state.value === PREVIEW_STATES.LOADING) return
    state.value = PREVIEW_STATES.LOADING
    try {
      const file = await fetchFile()
      blob = file.blob
      fileName.value = file.fileName
      revoke(objectUrl.value)
      objectUrl.value = objectUrlOf(file.blob)
      state.value = PREVIEW_STATES.READY
    } catch {
      state.value = PREVIEW_STATES.ERROR
    }
  }

  async function download() {
    if (downloading.value) return
    downloading.value = true
    try {
      const file = blob ? { blob, fileName: fileName.value } : await fetchFile()
      downloadBlob(file.blob, file.fileName)
    } catch (error) {
      toast.error(financeErrorMessage(i18n, error, 'finance.downloads.failed'))
    } finally {
      downloading.value = false
    }
  }

  function zoomIn() {
    zoom.value = Math.min(PROOF_ZOOM.MAX, zoom.value + PROOF_ZOOM.STEP)
  }

  function zoomOut() {
    zoom.value = Math.max(PROOF_ZOOM.MIN, zoom.value - PROOF_ZOOM.STEP)
  }

  onBeforeUnmount(() => revoke(objectUrl.value))

  return {
    state,
    objectUrl,
    zoom,
    downloading,
    canZoomIn,
    canZoomOut,
    load,
    download,
    zoomIn,
    zoomOut,
  }
}
