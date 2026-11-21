import { reactive } from 'vue'
import { identityService } from '@/services/identityService'

export function useIdentityChangeDetail() {
  const detail = reactive({ open: false, status: 'idle', change: null })

  async function view(change) {
    detail.open = true
    detail.status = 'loading'
    detail.change = null
    try {
      const { data } = await identityService.reviewDetail(change.id)
      detail.change = data
      detail.status = 'success'
    } catch {
      detail.status = 'error'
    }
  }

  return { detail, view }
}
