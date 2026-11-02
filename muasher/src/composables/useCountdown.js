import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { COUNTDOWN_TICK_MS } from '@/constants/payments'
import { countdownLabel, secondsUntil } from '@/utils/payments/paymentReturn'

export function useCountdown(deadline) {
  const now = ref(Date.now())
  let timer = null

  const remaining = computed(() => secondsUntil(deadline.value, now.value))
  const expired = computed(() => Boolean(deadline.value) && remaining.value === 0)
  const label = computed(() => countdownLabel(remaining.value))

  function stop() {
    clearInterval(timer)
    timer = null
  }

  function tick() {
    now.value = Date.now()
    if (expired.value) stop()
  }

  watch(
    deadline,
    (value) => {
      stop()
      now.value = Date.now()
      if (value && !expired.value) timer = setInterval(tick, COUNTDOWN_TICK_MS)
    },
    { immediate: true },
  )

  onBeforeUnmount(stop)

  return { remaining, expired, label }
}
