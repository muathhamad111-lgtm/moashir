import { prefersReducedMotion } from '@/utils/motion'
import { hasFinePointer } from '@/utils/landing/pointer'

const TILT_DEGREES = 12

function tiltMove(el, event) {
  const box = el.getBoundingClientRect()
  const x = (event.clientX - box.left) / box.width - 0.5
  const y = (event.clientY - box.top) / box.height - 0.5
  el.style.setProperty('--lp-tilt-x', `${(-y * TILT_DEGREES).toFixed(2)}deg`)
  el.style.setProperty('--lp-tilt-y', `${(x * TILT_DEGREES).toFixed(2)}deg`)
  el.classList.add('is-tilting')
}

function tiltLeave(el) {
  el.classList.remove('is-tilting')
  el.style.removeProperty('--lp-tilt-x')
  el.style.removeProperty('--lp-tilt-y')
}

export const landingTilt = {
  mounted(el) {
    if (prefersReducedMotion() || !hasFinePointer()) return
    el._lpTilt = { move: (event) => tiltMove(el, event), leave: () => tiltLeave(el) }
    el.addEventListener('pointermove', el._lpTilt.move)
    el.addEventListener('pointerleave', el._lpTilt.leave)
  },
  unmounted(el) {
    if (!el._lpTilt) return
    el.removeEventListener('pointermove', el._lpTilt.move)
    el.removeEventListener('pointerleave', el._lpTilt.leave)
  },
}

export const landingSpot = {
  mounted(el) {
    el._lpSpot = (event) => {
      const box = el.getBoundingClientRect()
      el.style.setProperty('--mx', `${event.clientX - box.left}px`)
      el.style.setProperty('--my', `${event.clientY - box.top}px`)
    }
    el.addEventListener('pointermove', el._lpSpot)
  },
  unmounted(el) {
    el.removeEventListener('pointermove', el._lpSpot)
  },
}
