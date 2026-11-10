import { computed, ref, shallowRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { awardCycleService } from '@/services/awardCycleService'
import { advancedService } from '@/services/advancedService'
import { useAuthStore } from '@/stores/auth'
import { usePermissions } from '@/composables/usePermissions'
import { useToast } from '@/composables/useToast'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { AWARD_CODES } from '@/constants/advanced'
import { PATH_CODES } from '@/constants/entityPaths'
import { ROUTE_NAMES } from '@/constants/routeNames'

const OPEN_ORDER_CODE = 'payment.open_order_exists'

function redirectOf(normalized, cycleId) {
  if (normalized.code === PATH_CODES.PAYMENT_REQUIRED)
    return {
      name: ROUTE_NAMES.ASSESSMENT_ADVANCED_PAY,
      query: { cycle: String(normalized.meta?.awardCycleId ?? cycleId) },
    }
  return normalized.code === OPEN_ORDER_CODE ? { name: ROUTE_NAMES.PATHS_AWARD } : null
}

export function useAdvancedIntro() {
  const { t } = useI18n()
  const router = useRouter()
  const auth = useAuthStore()
  const toast = useToast()
  const { can } = usePermissions()
  const cycleStatus = ref('loading')
  const status = ref('idle')
  const errorMessage = ref('')
  const cycle = shallowRef(null)
  const current = shallowRef(null)
  const applying = ref(false)
  const rejection = shallowRef(null)

  const isAuthenticated = computed(() => auth.isAuthenticated)
  const isEntity = computed(
    () =>
      isAuthenticated.value &&
      Boolean(auth.user?.entityId) &&
      can(PERMISSIONS.ASSESSMENT_ADVANCED_MANAGE),
  )

  async function loadCycle() {
    cycleStatus.value = 'loading'
    try {
      cycle.value = (await awardCycleService.getCurrent()).data
      cycleStatus.value = cycle.value ? 'ready' : 'empty'
    } catch {
      cycleStatus.value = 'error'
    }
  }

  async function loadCurrent() {
    if (!isEntity.value) return
    status.value = 'loading'
    errorMessage.value = ''
    try {
      current.value = (await advancedService.getCurrent()).data
      status.value = 'ready'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  function load() {
    return Promise.all([loadCycle(), loadCurrent()])
  }

  function rejectionOf(error) {
    const normalized = error?.normalized ?? {}
    if (normalized.code === AWARD_CODES.CONDITIONS_FAILED)
      return { code: normalized.code, conditions: normalized.meta?.conditions ?? [] }
    if (normalized.code === AWARD_CODES.REQUEST_EXISTS)
      return { code: normalized.code, assessmentId: normalized.meta?.assessmentId ?? null }
    return { code: normalized.code ?? '', message: normalized.message ?? '' }
  }

  async function apply() {
    if (applying.value || !current.value?.cycle) return null
    applying.value = true
    rejection.value = null
    const cycleId = current.value.cycle.id
    try {
      const { data } = await advancedService.apply({ awardCycleId: cycleId })
      return data
    } catch (error) {
      const redirect = redirectOf(error?.normalized ?? {}, cycleId)
      if (redirect) {
        await router.push(redirect)
        return null
      }
      rejection.value = rejectionOf(error)
      toast.error(error?.normalized?.message || t('errors.unexpected'))
      await loadCurrent()
      return null
    } finally {
      applying.value = false
    }
  }

  return {
    cycleStatus,
    status,
    errorMessage,
    cycle,
    current,
    applying,
    rejection,
    isAuthenticated,
    isEntity,
    load,
    loadCurrent,
    apply,
  }
}
