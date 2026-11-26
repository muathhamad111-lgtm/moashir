import { prefersReducedMotion } from '@/utils/motion'

const VISIBLE_RATIO = 0.12
const PENDING = 'is-reveal-pending'
const REVEALED = 'is-revealed'
const STAGGER = 'reveal-stagger'

function show(el) {
  el.classList.remove(PENDING)
  el.classList.add(REVEALED)
}

function observe(el) {
  const observer = new IntersectionObserver(
    (entries) => {
      if (!entries.some((entry) => entry.isIntersecting)) return
      observer.disconnect()
      el._revealObserver = null
      show(el)
    },
    { threshold: VISIBLE_RATIO },
  )
  el._revealObserver = observer
  observer.observe(el)
}

export const reveal = {
  mounted(el, binding) {
    if (binding.value?.stagger) el.classList.add(STAGGER)
    if (prefersReducedMotion() || typeof IntersectionObserver !== 'function') {
      show(el)
      return
    }
    el.classList.add(PENDING)
    observe(el)
  },
  unmounted(el) {
    el._revealObserver?.disconnect()
    el._revealObserver = null
  },
}
