import { beforeEach, describe, expect, it, vi } from 'vitest'
import { BUILD_POSTURES } from '@/utils/build/buildPosture'

const startMockWorker = vi.fn(async () => {})

vi.mock('@/mocks/browser', () => ({ startMockWorker }))

const { enableMocking } = await import('@/mocks')

beforeEach(() => {
  startMockWorker.mockClear()
})

describe('enableMocking', () => {
  it('starts the whole mock layer without an API base URL', async () => {
    await enableMocking(BUILD_POSTURES.MOCK)
    expect(startMockWorker).toHaveBeenCalledTimes(1)
  })

  it.each([BUILD_POSTURES.DEMO, BUILD_POSTURES.REAL])(
    'never loads the mock worker against the API in the %s posture',
    async (posture) => {
      await enableMocking(posture)
      expect(startMockWorker).not.toHaveBeenCalled()
    },
  )
})
