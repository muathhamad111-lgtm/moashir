import { computed, onUnmounted, ref } from 'vue'

export function useOtpChallenge() {
  const challenge = ref(null)
  const now = ref(Date.now())
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
    start()
  }

  const length = computed(() => challenge.value?.length ?? 6)
  const channel = computed(() => challenge.value?.channel ?? 'email')
  const challengeId = computed(() => challenge.value?.challengeId ?? null)

  const expirySecondsLeft = computed(() => {
    if (!challenge.value?.expiresAt) return 0
    const diff = new Date(challenge.value.expiresAt).getTime() - now.value
    return Math.max(0, Math.ceil(diff / 1000))
  })

  const resendSecondsLeft = computed(() => {
    if (!challenge.value?.resendAvailableAt) return 0
    const diff = new Date(challenge.value.resendAvailableAt).getTime() - now.value
    return Math.max(0, Math.ceil(diff / 1000))
  })

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
    expired,
    canResend,
    stop,
  }
}
