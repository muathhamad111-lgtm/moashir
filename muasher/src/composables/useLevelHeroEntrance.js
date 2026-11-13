import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  LEVEL_HERO_MOTION as MOTION,
  LEVEL_HERO_PHASES as PHASES,
} from '@/constants/levelHeroMotion'
import { STORAGE_KEYS } from '@/constants/storageKeys'
import { cubicBezier } from '@/utils/cubicBezier'
import { prefersReducedMotion } from '@/utils/motion'
import { readSessionStored, writeSessionStored } from '@/utils/safeStorage'
import { tweenNumber } from '@/utils/tweenNumber'

const PLAYED = '1'
const easeBrand = cubicBezier(MOTION.easeBrand)

function storageKeyOf(key) {
  return `${STORAGE_KEYS.levelHeroEntrance}.${key}`
}

function canAnimate() {
  return (
    !prefersReducedMotion() &&
    typeof IntersectionObserver === 'function' &&
    typeof requestAnimationFrame === 'function'
  )
}

export function useLevelHeroEntrance(rootRef, { ready, playKey, score }) {
  const phase = ref(PHASES.STATIC)
  const counted = ref(null)
  let observer = null
  let cancelTween = null
  let finishTimer = null
  let armed = false

  const displayScore = computed(() =>
    phase.value === PHASES.STATIC || counted.value === null ? score.value : counted.value,
  )

  function finish() {
    phase.value = PHASES.STATIC
    counted.value = null
  }

  function play() {
    writeSessionStored(storageKeyOf(playKey.value), PLAYED)
    if (document.visibilityState === 'hidden') return finish()
    phase.value = PHASES.PLAYING
    if (score.value !== null) {
      cancelTween = tweenNumber({
        to: Number(score.value),
        delay: MOTION.countDelayMs,
        duration: MOTION.countDurationMs,
        easing: easeBrand,
        onUpdate: (value) => (counted.value = value),
      })
    }
    finishTimer = setTimeout(finish, MOTION.totalMs)
  }

  function observe() {
    observer = new IntersectionObserver(
      (entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return
        observer.disconnect()
        observer = null
        play()
      },
      { threshold: MOTION.visibleShare },
    )
    observer.observe(rootRef.value)
  }

  function arm() {
    if (armed || !rootRef.value || !canAnimate()) return
    armed = true
    if (readSessionStored(storageKeyOf(playKey.value)) === PLAYED) return
    phase.value = PHASES.PENDING
    observe()
  }

  onMounted(() => {
    watch(ready, (isReady) => isReady && arm(), { immediate: true, flush: 'post' })
  })

  onBeforeUnmount(() => {
    observer?.disconnect()
    cancelTween?.()
    clearTimeout(finishTimer)
  })

  return { phase, displayScore }
}
