import { onMounted, ref, unref, watch } from 'vue'
import { certificatesService } from '@/services/certificatesService'

const RATE_LIMITED = 429

export function useCertificateVerification(code) {
  const verification = ref(null)
  const status = ref('idle')

  async function load() {
    const value = unref(code)
    verification.value = null
    if (!value) {
      status.value = 'idle'
      return
    }
    status.value = 'loading'
    try {
      verification.value = (await certificatesService.verify(value)).data
      status.value = 'success'
    } catch (error) {
      status.value = error?.normalized?.status === RATE_LIMITED ? 'rate-limited' : 'error'
    }
  }

  watch(() => unref(code), load)
  onMounted(load)

  return { verification, status, load }
}
