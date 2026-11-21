import { describe, expect, it, vi } from 'vitest'
import { nextTick, ref } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { useCompleteMessageKey } from '@/composables/useCertificateIssuanceMode'
import { certificatesService } from '@/services/certificatesService'

vi.mock('@/services/certificatesService', () => ({
  certificatesService: { requestState: vi.fn() },
}))

describe('useCompleteMessageKey', () => {
  it('reads the mode once the request is known to be accredited and picks the manual message', async () => {
    certificatesService.requestState.mockResolvedValue({
      data: { certificate: null, issuanceMode: 'manual' },
    })
    const offersCertificate = ref(false)
    const key = useCompleteMessageKey({
      assessmentId: ref(9104),
      offersCertificate,
      hasPendingChanges: ref(false),
    })
    expect(certificatesService.requestState).not.toHaveBeenCalled()
    offersCertificate.value = true
    await nextTick()
    await flushPromises()
    expect(certificatesService.requestState).toHaveBeenCalledWith(9104)
    expect(key.value).toBe('review.confirmComplete.manualCertificateMessage')
  })

  it('stays neutral when the mode cannot be read', async () => {
    certificatesService.requestState.mockRejectedValue(new Error('offline'))
    const key = useCompleteMessageKey({
      assessmentId: ref(9104),
      offersCertificate: ref(true),
      hasPendingChanges: ref(false),
    })
    await flushPromises()
    expect(key.value).toBe('review.confirmComplete.message')
  })
})
