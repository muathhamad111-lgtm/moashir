import { prefersReducedMotion } from '@/utils/motion'

export const AUTH_MOTION = Object.freeze({
  leaveMs: 170,
  enterMs: 420,
  resizeMs: 460,
  settleMs: 1600,
  shiftPx: 32,
  // In-page panels (the sign-in method forms) slide whole, in step with the tab pill.
  panelMs: 350,
  panelGapPx: 24,
  panelEase: 'ease-out',
  // How long an accepted code shows its success state before the next screen opens.
  verifiedHoldMs: 700,
  easeOut: 'cubic-bezier(0.22, 1, 0.36, 1)',
  easeIn: 'cubic-bezier(0.55, 0, 1, 0.45)',
})

export function canAnimate(element) {
  return typeof element?.animate === 'function'
}

export function slideDirection(fromIndex, toIndex) {
  if (fromIndex < 0 || toIndex < 0 || prefersReducedMotion()) return 0
  return toIndex >= fromIndex ? 1 : -1
}

function inlineSign() {
  return document.documentElement.dir === 'rtl' ? -1 : 1
}

function offset(direction) {
  return AUTH_MOTION.shiftPx * direction * inlineSign()
}

export function finishAfterPatch(done) {
  queueMicrotask(done)
}

// The leaving form stays on screen while it fades; inert keeps it from taking typing or a submit.
export function slideOut(element, direction, done) {
  if (!direction || !canAnimate(element)) return finishAfterPatch(done)
  element.inert = true
  element
    .animate(
      [
        { opacity: 1, translate: '0 0' },
        { opacity: 0, translate: `${-offset(direction)}px 0` },
      ],
      { duration: AUTH_MOTION.leaveMs, easing: AUTH_MOTION.easeIn, fill: 'forwards' },
    )
    .finished.then(done, done)
}

export function slideIn(element, direction, done) {
  if (!direction || !canAnimate(element)) return done()
  element
    .animate(
      [
        { opacity: 0, translate: `${offset(direction)}px 0` },
        { opacity: 1, translate: '0 0' },
      ],
      { duration: AUTH_MOTION.enterMs, easing: AUTH_MOTION.easeOut },
    )
    .finished.then(done, done)
}

export const PANEL_LEAVING = 'is-panel-leaving'

function panelDistance(element, direction) {
  const width = element.getBoundingClientRect().width
  return (width + AUTH_MOTION.panelGapPx) * direction * inlineSign()
}

function swipe(element, keyframes, fill = 'none') {
  return element.animate(keyframes, {
    duration: AUTH_MOTION.panelMs,
    easing: AUTH_MOTION.panelEase,
    fill,
  }).finished
}

// The leaving panel lifts out of the flow so both panels travel side by side, like slides.
export function swipeOut(element, direction, done) {
  if (!direction || !canAnimate(element)) return finishAfterPatch(done)
  element.inert = true
  element.classList.add(PANEL_LEAVING)
  const distance = panelDistance(element, direction)
  swipe(
    element,
    [
      { opacity: 1, translate: '0 0' },
      { opacity: 0.2, translate: `${-distance}px 0` },
    ],
    'forwards',
  ).then(done, done)
}

export function swipeIn(element, direction, done) {
  if (!direction || !canAnimate(element)) return done()
  const distance = panelDistance(element, direction)
  swipe(element, [
    { opacity: 0.2, translate: `${distance}px 0` },
    { opacity: 1, translate: '0 0' },
  ]).then(done, done)
}

export function holdVerified() {
  const ms = prefersReducedMotion() ? 0 : AUTH_MOTION.verifiedHoldMs
  return ms ? new Promise((resolve) => setTimeout(resolve, ms)) : Promise.resolve()
}
