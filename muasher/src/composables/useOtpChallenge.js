import { computed, onUnmounted, ref } from 'vue'

const SECONDS_PER_MINUTE = 60

function secondsUntil(timestamp, now) {
  if (!timestamp) return 0
  return Math.max(0, Math.ceil((new Date(timestamp).getTime() - now) / 1000))
}

export function useOtpChallenge() {
  const challenge = ref(null)
  const now = ref(Date.now())
  const validitySeconds = ref(0)
  let timer = null

  function start() {
    stop()
    timer = setInterval(() => {
      now.value = Date.now()
    }, 1000)
  }

  function stop() {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
  }

  function setChallenge(next) {
    challenge.value = next
    now.value = Date.now()
    validitySeconds.value = secondsUntil(next?.expiresAt, now.value)
    start()
  }

  const length = computed(() => challenge.value?.length ?? 6)
  const channel = computed(() => challenge.value?.channel ?? 'email')
  const challengeId = computed(() => challenge.value?.challengeId ?? null)

  const expirySecondsLeft = computed(() => secondsUntil(challenge.value?.expiresAt, now.value))
  const resendSecondsLeft = computed(() =>
    secondsUntil(challenge.value?.resendAvailableAt, now.value),
  )
  const validityMinutes = computed(() =>
    Math.max(1, Math.round(validitySeconds.value / SECONDS_PER_MINUTE)),
  )

  const expired = computed(() => Boolean(challenge.value) && expirySecondsLeft.value === 0)
  const canResend = computed(() => resendSecondsLeft.value === 0)

  function formatClock(totalSeconds) {
    const minutes = Math.floor(totalSeconds / 60)
    const seconds = totalSeconds % 60
    return `${minutes}:${String(seconds).padStart(2, '0')}`
  }

  const expiryClock = computed(() => formatClock(expirySecondsLeft.value))

  onUnmounted(stop)

  return {
    challenge,
    setChallenge,
    length,
    channel,
    challengeId,
    expirySecondsLeft,
    resendSecondsLeft,
    expiryClock,
    validityMinutes,
    expired,
    canResend,
    stop,
  }
}
