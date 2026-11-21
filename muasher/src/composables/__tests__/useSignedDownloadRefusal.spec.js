import { beforeEach, describe, expect, it, vi } from 'vitest'
import { withSetup } from '@/test-utils/withSetup'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { useAccountCompletionStore } from '@/stores/accountCompletion'
import { toast } from '@/composables/useToast'

const REFUSAL = {
  normalized: {
    status: 403,
    code: 'national_id.required_for_files',
    message: 'refused',
    meta: { nationalIdStatus: 'missing' },
  },
}

beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(toast, 'error').mockImplementation(() => {})
})

describe('signed downloads refused for a missing national ID', () => {
  it('opens the refusal dialog instead of an error toast', async () => {
    const { api } = withSetup(() => useSignedDownload())
    const done = await api.download('evidence-1', () => Promise.reject(REFUSAL))
    expect(done).toBe(false)
    expect(useAccountCompletionStore().refusedFileAccess).toBe('missing')
    expect(toast.error).not.toHaveBeenCalled()
  })

  it('keeps the error toast for any other failure', async () => {
    const { api } = withSetup(() => useSignedDownload())
    await api.download('evidence-1', () =>
      Promise.reject({
        normalized: { status: 503, code: 'document.renderer_busy', message: 'busy' },
      }),
    )
    expect(useAccountCompletionStore().refusedFileAccess).toBeNull()
    expect(toast.error).toHaveBeenCalledOnce()
  })
})
