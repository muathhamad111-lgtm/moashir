import { computed, ref } from 'vue'
import { prefersReducedMotion } from '@/utils/motion'
import { clamp, easeInOut, easeOut, segment } from '@/utils/landing/easing'
import { PARTICIPATION, tierOfScore } from '@/utils/landing/tiers'
import { useScrollFrame } from './useScrollFrame'

const PEAK_SCORE = 95
const COPY_GAP = 28
const BOTTOM_GAP = 22
const SCALE_MIN = 0.26
const SCALE_MAX = 0.56

export function useHeroStage({ heroRef, pinRef, copyRef, trophyRef, tiers, onTierChange }) {
  const score = ref(0)
  const tierKey = ref('')
  const tier = computed(() => tierOfScore(tiers.value, score.value))
  let peek = 0
  let restScale = SCALE_MAX

  function setVar(name, value) {
    pinRef.value?.style.setProperty(name, value)
  }

  function measure() {
    const pin = pinRef.value
    const trophy = trophyRef.value
    const copy = copyRef.value
    if (!pin || !trophy || !copy) return
    setVar('--rise', '0px')
    setVar('--ts', '1')
    const trophyRect = trophy.getBoundingClientRect()
    const restBottom = trophyRect.bottom - pin.getBoundingClientRect().top
    const height = trophyRect.height || 1
    const copyBottom = copy.offsetTop + copy.offsetHeight
    const available = window.innerHeight - BOTTOM_GAP - (copyBottom + COPY_GAP)
    restScale = clamp(Math.min(SCALE_MAX, available / height), SCALE_MIN, SCALE_MAX)
    peek = copyBottom + COPY_GAP + height * restScale - restBottom
  }

  function progress() {
    const hero = heroRef.value
    if (!hero) return 0
    const range = hero.offsetHeight - window.innerHeight
    return range > 0 ? clamp(-hero.getBoundingClientRect().top / range) : 0
  }

  function render() {
    if (prefersReducedMotion() || !pinRef.value) return
    const p = progress()
    const up = easeInOut(segment(p, 0.07, 0.32))
    setVar('--co', easeOut(segment(p, 0, 0.1)).toFixed(3))
    setVar('--ri', segment(p, 0.22, 0.32).toFixed(3))
    setVar('--sv', segment(p, 0.18, 0.32).toFixed(3))
    setVar('--rise', `${(peek * (1 - up)).toFixed(1)}px`)
    setVar('--ts', (restScale + (1 - restScale) * up).toFixed(3))
    score.value = Math.round(PEAK_SCORE * easeInOut(segment(p, 0.32, 0.9)))
    syncTier()
  }

  function syncTier() {
    const current = tier.value
    if (!current || current.key === tierKey.value) return
    const previous = tierKey.value
    tierKey.value = current.key
    setVar('--tier-ink', current.ink)
    setVar('--glow', current.glow)
    if (previous && current.key !== PARTICIPATION) onTierChange?.(current)
  }

  const { schedule } = useScrollFrame(render, { onResize: measure })

  function start() {
    measure()
    schedule()
  }

  return { score, tier, start }
}
