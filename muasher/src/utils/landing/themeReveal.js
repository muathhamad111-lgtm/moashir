import { prefersReducedMotion } from '@/utils/motion'
import { easeInOutQuad } from '@/utils/landing/easing'

const DURATION_MS = 820
const MAX_STEP_MS = 22
const RUNNING = 'lp-vt-run'
const VARIABLES = ['--lp-vtx', '--lp-vty', '--lp-vtr']
const CHROMIUM = /Chrome|Chromium|Edg\//

function supportsReveal() {
  return (
    typeof document.startViewTransition === 'function' &&
    CHROMIUM.test(navigator.userAgent) &&
    !prefersReducedMotion()
  )
}

function coverRadius(x, y) {
  return Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y)) + 2
}

function grow(root, from, to, transition) {
  let progress = 0
  let last = performance.now()
  const step = (now) => {
    progress = Math.min(1, progress + Math.min(now - last, MAX_STEP_MS) / DURATION_MS)
    last = now
    const radius = from + (to - from) * Math.sqrt(easeInOutQuad(progress))
    root.style.setProperty('--lp-vtr', `${radius.toFixed(1)}px`)
    if (progress < 1) requestAnimationFrame(step)
    else requestAnimationFrame(() => transition.skipTransition())
  }
  requestAnimationFrame(step)
}

export function revealThemeChange(origin, apply) {
  if (!origin || !supportsReveal()) {
    apply()
    return
  }
  const root = document.documentElement
  const box = origin.getBoundingClientRect()
  const x = box.left + box.width / 2
  const y = box.top + box.height / 2
  const start = box.width / 2
  root.style.setProperty('--lp-vtx', `${x}px`)
  root.style.setProperty('--lp-vty', `${y}px`)
  root.style.setProperty('--lp-vtr', `${start}px`)
  root.classList.add(RUNNING)
  const transition = document.startViewTransition(apply)
  transition.ready.then(() => grow(root, start, coverRadius(x, y), transition)).catch(() => {})
  transition.finished.finally(() => {
    root.classList.remove(RUNNING)
    VARIABLES.forEach((name) => root.style.removeProperty(name))
  })
}
