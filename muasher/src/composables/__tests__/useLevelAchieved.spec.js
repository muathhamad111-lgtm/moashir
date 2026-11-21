import { beforeEach, describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useLevelAchieved } from '@/composables/useLevelAchieved'
import { resolveLevelAchievedSince } from '@/config/levelAchieved'
import { sessionUserFor } from '@/test-utils/session'

const SINCE = Date.parse('2026-11-06T00:00:00+03:00')

function certificateIssuedAt(issuedAt, id = 'c-1') {
  return ref({ id, level: 'gold', issuedAt })
}

beforeEach(() => {
  localStorage.clear()
  setActivePinia(createPinia())
  useAuthStore().setUser(sessionUserFor(['entity'], { id: 'u-1' }))
})

describe('useLevelAchieved', () => {
  it('stays closed for a certificate issued before the cut-off', () => {
    const { isOpen } = useLevelAchieved(certificateIssuedAt('2026-11-05T20:59:59Z'), {
      since: SINCE,
    })
    expect(isOpen.value).toBe(false)
  })

  it('opens for a certificate issued on the cut-off day', () => {
    const { isOpen } = useLevelAchieved(certificateIssuedAt('2026-11-05T21:00:00Z'), {
      since: SINCE,
    })
    expect(isOpen.value).toBe(true)
  })

  it('opens for a certificate issued after the cut-off, once per certificate and user', () => {
    const certificate = certificateIssuedAt('2026-11-20T08:00:00Z')
    const first = useLevelAchieved(certificate, { since: SINCE })
    expect(first.isOpen.value).toBe(true)
    first.acknowledge()
    expect(first.isOpen.value).toBe(false)
    expect(useLevelAchieved(certificate, { since: SINCE }).isOpen.value).toBe(false)

    certificate.value = { id: 'c-2', level: 'diamond', issuedAt: '2026-12-01T08:00:00Z' }
    expect(useLevelAchieved(certificate, { since: SINCE }).isOpen.value).toBe(true)

    useAuthStore().setUser(sessionUserFor(['entity'], { id: 'u-2' }))
    certificate.value = { id: 'c-1', level: 'gold', issuedAt: '2026-11-20T08:00:00Z' }
    expect(useLevelAchieved(certificate, { since: SINCE }).isOpen.value).toBe(true)
  })

  it.each([
    ['missing', undefined],
    ['invalid', '2026-02-30'],
  ])('never opens when the setting is %s', (_, setting) => {
    const since = resolveLevelAchievedSince(setting, () => {})
    const { isOpen } = useLevelAchieved(certificateIssuedAt('2026-11-20T08:00:00Z'), { since })
    expect(isOpen.value).toBe(false)
  })

  it('stays closed without a certificate', () => {
    const { isOpen, acknowledge } = useLevelAchieved(ref(null), { since: SINCE })
    acknowledge()
    expect(isOpen.value).toBe(false)
  })
})
