import { ref } from 'vue'
import { prefersReducedMotion } from '@/utils/motion'
import { clamp } from '@/utils/landing/easing'
import { useScrollFrame } from './useScrollFrame'

const START_LINE = 0.88
const SPAN = 0.38
const OVERSHOOT = 1.12

export function useManifesto(rootRef, wordCount) {
  const lit = ref(0)

  useScrollFrame(() => {
    const total = wordCount()
    if (prefersReducedMotion() || !rootRef.value) {
      lit.value = total
      return
    }
    const box = rootRef.value.getBoundingClientRect()
    const progress = clamp(
      (window.innerHeight * START_LINE - box.top) / (box.height + window.innerHeight * SPAN),
    )
    lit.value = Math.round(progress * total * OVERSHOOT)
  })

  return { lit }
}
