import { ref } from 'vue'
import {
  errorCodeOf,
  failedResultOf,
  firstFieldMessage,
  providerErrorKindOf,
  retryAfterSecondsOf,
  testFailureOf,
} from '@/utils/integrations/providerError'

export const PROVIDER_ACTION_STATES = Object.freeze({
  IDLE: 'idle',
  PENDING: 'pending',
  SUCCESS: 'success',
  FAILED: 'failed',
})

function failureOf(error) {
  return {
    type: testFailureOf(error),
    code: errorCodeOf(error),
    kind: providerErrorKindOf(error),
    retryAfter: retryAfterSecondsOf(error),
    result: failedResultOf(error),
    message: error?.normalized?.message ?? '',
    fieldMessage: firstFieldMessage(error),
  }
}

export function useProviderAction(run, { onSettled } = {}) {
  const state = ref(PROVIDER_ACTION_STATES.IDLE)
  const result = ref(null)
  const failure = ref(null)

  async function execute(...args) {
    if (state.value === PROVIDER_ACTION_STATES.PENDING) return null
    state.value = PROVIDER_ACTION_STATES.PENDING
    failure.value = null
    try {
      const envelope = await run(...args)
      result.value = envelope?.data ?? null
      state.value = PROVIDER_ACTION_STATES.SUCCESS
      return result.value
    } catch (error) {
      result.value = null
      failure.value = failureOf(error)
      state.value = PROVIDER_ACTION_STATES.FAILED
      return null
    } finally {
      onSettled?.()
    }
  }

  function reset() {
    state.value = PROVIDER_ACTION_STATES.IDLE
    result.value = null
    failure.value = null
  }

  return { state, result, failure, execute, reset }
}
