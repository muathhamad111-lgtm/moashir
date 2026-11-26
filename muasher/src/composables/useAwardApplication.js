import { ref, shallowRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { advancedService } from '@/services/advancedService'
import { useToast } from '@/composables/useToast'
import { AWARD_CODES } from '@/constants/advanced'
import { PATH_CODES } from '@/constants/entityPaths'
import { ROUTE_NAMES } from '@/constants/routeNames'

const OPEN_ORDER_CODE = 'payment.open_order_exists'

// A paid cycle sends the entity to its checkout first; an order already open keeps it on the award path page.
function redirectOf(normalized, cycleId) {
  if (normalized.code === PATH_CODES.PAYMENT_REQUIRED)
    return {
      name: ROUTE_NAMES.ASSESSMENT_ADVANCED_PAY,
      query: { cycle: String(normalized.meta?.awardCycleId ?? cycleId) },
    }
  return normalized.code === OPEN_ORDER_CODE ? { name: ROUTE_NAMES.PATHS_AWARD } : null
}

function rejectionOf(normalized) {
  if (normalized.code === AWARD_CODES.CONDITIONS_FAILED)
    return { code: normalized.code, conditions: normalized.meta?.conditions ?? [] }
  if (normalized.code === AWARD_CODES.REQUEST_EXISTS)
    return { code: normalized.code, assessmentId: normalized.meta?.assessmentId ?? null }
  return { code: normalized.code ?? '', message: normalized.message ?? '' }
}

// The entity applies for the running cycle from its award path page (03 §8.1, §8.9). The server checks the
// eligibility; a rejection is shown and recorded, and the caller reloads the path to show the new state.
export function useAwardApplication() {
  const { t } = useI18n()
  const router = useRouter()
  const toast = useToast()
  const applying = ref(false)
  const rejection = shallowRef(null)

  async function apply(cycleId) {
    if (applying.value || !cycleId) return null
    applying.value = true
    rejection.value = null
    try {
      const { data } = await advancedService.apply({ awardCycleId: cycleId })
      return data
    } catch (error) {
      const normalized = error?.normalized ?? {}
      const redirect = redirectOf(normalized, cycleId)
      if (redirect) {
        await router.push(redirect)
        return null
      }
      rejection.value = rejectionOf(normalized)
      toast.error(normalized.message || t('errors.unexpected'))
      return null
    } finally {
      applying.value = false
    }
  }

  return { applying, rejection, apply }
}
