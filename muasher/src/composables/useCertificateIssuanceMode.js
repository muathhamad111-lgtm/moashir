import { computed, ref, unref, watch } from 'vue'
import { certificatesService } from '@/services/certificatesService'
import { completeMessageKey } from '@/utils/review/completeMessage'

// The assigned assessor's view of how the accredited certificate will be issued; null when unknown.
export function useCertificateIssuanceMode(assessmentId, enabled) {
  const mode = ref(null)

  async function load() {
    if (!unref(enabled)) return
    try {
      mode.value = (await certificatesService.requestState(unref(assessmentId))).data.issuanceMode
    } catch {
      mode.value = null
    }
  }

  // The request loads after mount, so the mode is read once its path is known.
  watch(
    () => unref(enabled),
    (isEnabled, wasEnabled) => {
      if (isEnabled && !wasEnabled) load()
    },
    { immediate: true },
  )

  return { mode, load }
}

// The completion question's message key for the assessor review.
export function useCompleteMessageKey({ assessmentId, offersCertificate, hasPendingChanges }) {
  const { mode } = useCertificateIssuanceMode(assessmentId, offersCertificate)
  return computed(() =>
    completeMessageKey({
      hasPendingChanges: hasPendingChanges.value,
      offersCertificate: offersCertificate.value,
      issuanceMode: mode.value,
    }),
  )
}
