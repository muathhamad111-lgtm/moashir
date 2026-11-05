import { useAuthStore } from '@/stores/auth'
import { useActionCenterStore } from '@/stores/actionCenter'
import { usePortalCountsStore } from '@/stores/portalCounts'
import { useEntityStore } from '@/stores/entity'

export function usePortalSession() {
  const auth = useAuthStore()
  const actionCenter = useActionCenterStore()
  const counts = usePortalCountsStore()
  const entity = useEntityStore()

  async function signOut() {
    await auth.logout()
    actionCenter.reset()
    counts.reset()
    entity.reset()
  }

  return { signOut }
}
