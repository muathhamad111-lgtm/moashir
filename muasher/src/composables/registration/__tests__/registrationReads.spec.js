import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  prefetchRegistrationReads,
  takeRegistrationRead,
} from '@/composables/registration/registrationReads'
import { clearPrefetchedReads } from '@/utils/prefetchedReads'
import { termsService } from '@/services/termsService'
import { regionsService } from '@/services/regionsService'
import { identityService } from '@/services/identityService'

vi.mock('@/services/termsService', () => ({ termsService: { current: vi.fn() } }))
vi.mock('@/services/regionsService', () => ({ regionsService: { listRegions: vi.fn() } }))
vi.mock('@/services/identityService', () => ({
  identityService: { registrationOptions: vi.fn() },
}))

describe('registrationReads', () => {
  afterEach(() => {
    clearPrefetchedReads()
    vi.clearAllMocks()
  })

  it('prefetches the terms, the regions and the options once for the register page', async () => {
    termsService.current.mockResolvedValue({ data: { terms: 't' } })
    regionsService.listRegions.mockResolvedValue({ data: [] })
    identityService.registrationOptions.mockResolvedValue({ data: { nationalIdRequired: true } })
    prefetchRegistrationReads()
    await expect(takeRegistrationRead('terms')).resolves.toEqual({ data: { terms: 't' } })
    await expect(takeRegistrationRead('regions')).resolves.toEqual({ data: [] })
    await expect(takeRegistrationRead('options')).resolves.toEqual({
      data: { nationalIdRequired: true },
    })
    expect(termsService.current).toHaveBeenCalledTimes(1)
    expect(regionsService.listRegions).toHaveBeenCalledTimes(1)
    expect(identityService.registrationOptions).toHaveBeenCalledTimes(1)
  })

  it('reads straight from the services when nothing was prefetched', async () => {
    termsService.current.mockResolvedValue({ data: null })
    await takeRegistrationRead('terms')
    expect(termsService.current).toHaveBeenCalledTimes(1)
  })
})
