import { computed, ref, shallowRef } from 'vue'
import { requestsService } from '@/services/requestsService'
import { usePermissions } from '@/composables/usePermissions'
import { useAuthStore } from '@/stores/auth'
import { PERMISSIONS } from '@/constants/permissionKeys'
import {
  isAssignable,
  isReassignable,
  isReturnable,
  isWithdrawable,
} from '@/utils/assessments/reviewStages'

const NOT_FOUND = 404

export function useRequestDetail(requestId) {
  const { can } = usePermissions()
  const auth = useAuthStore()
  const status = ref('loading')
  const errorMessage = ref('')
  const detail = shallowRef(null)

  const active = computed(() => Boolean(detail.value) && !detail.value.isFrozen)
  const follows = computed(() => auth.isSuperAdmin || can(PERMISSIONS.REQUESTS_COMPLETION_RETURN))
  const canAssign = computed(() => active.value && isAssignable(detail.value))
  const canReassign = computed(() => active.value && isReassignable(detail.value))
  const canReturn = computed(() => active.value && follows.value && isReturnable(detail.value))
  const canWithdraw = computed(
    () => Boolean(detail.value) && follows.value && isWithdrawable(detail.value),
  )

  async function load({ silent = false } = {}) {
    if (!silent) status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await requestsService.get(requestId.value)
      detail.value = data
      status.value = 'ready'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = error?.normalized?.status === NOT_FOUND ? 'missing' : 'error'
    }
  }

  function apply(next) {
    detail.value = next
  }

  return {
    status,
    errorMessage,
    detail,
    canAssign,
    canReassign,
    canReturn,
    canWithdraw,
    load,
    apply,
  }
}
