import { useAuthStore } from '@/stores/auth'
import { useActionCenterStore } from '@/stores/actionCenter'
import { usePortalCountsStore } from '@/stores/portalCounts'
import { useEntityStore } from '@/stores/entity'
import { useEntityLevelStore } from '@/stores/entityLevel'
import { useEntityPathsStore } from '@/stores/entityPaths'

export function usePortalSession() {
  const auth = useAuthStore()
  const actionCenter = useActionCenterStore()
  const counts = usePortalCountsStore()
  const entity = useEntityStore()
  const level = useEntityLevelStore()
  const paths = useEntityPathsStore()

  async function signOut() {
    await auth.logout()
    actionCenter.reset()
    counts.reset()
    entity.reset()
    level.reset()
    paths.reset()
  }

  return { signOut }
}
