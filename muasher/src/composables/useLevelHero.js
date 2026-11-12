import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useEntityLevel } from '@/composables/useEntityLevel'
import { useCertificateLevels } from '@/composables/useCertificateLevels'
import { usePermissions } from '@/composables/usePermissions'
import { accessFor } from '@/constants/routeAccess'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { NEXT_LEVEL_TIERS } from '@/constants/levels'
import { hasOpenAccredited, heroActionOf } from '@/utils/levelTier'

const PENDING_STATUSES = ['idle', 'loading']
const NEXT_LEVEL_ROUTE = Object.freeze({ name: ROUTE_NAMES.PORTAL_TOOL_STRENGTHS })

export function useLevelHero() {
  const auth = useAuthStore()
  const level = useEntityLevel()
  const levels = useCertificateLevels()
  const { canAccess } = usePermissions()

  const certificate = computed(() => level.certificate.value)
  const action = computed(() =>
    heroActionOf({
      tier: level.tier.value,
      validity: certificate.value?.validity ?? null,
      hasOpenRequest: hasOpenAccredited(level.accredited.value),
    }),
  )
  const nextLevelTo = computed(() =>
    NEXT_LEVEL_TIERS.includes(level.tier.value) &&
    canAccess(accessFor(ROUTE_NAMES.PORTAL_TOOL_STRENGTHS))
      ? NEXT_LEVEL_ROUTE
      : null,
  )
  const playKey = computed(
    () => `${auth.user?.id ?? 'guest'}.${certificate.value?.id ?? level.tier.value}`,
  )
  const loading = computed(
    () =>
      PENDING_STATUSES.includes(level.status.value) ||
      PENDING_STATUSES.includes(levels.status.value),
  )

  return {
    tier: level.tier,
    certificate,
    ranges: levels.ranges,
    reassessment: level.reassessment,
    action,
    nextLevelTo,
    playKey,
    loading,
    failed: computed(() => level.status.value === 'error'),
    retry: level.load,
  }
}
