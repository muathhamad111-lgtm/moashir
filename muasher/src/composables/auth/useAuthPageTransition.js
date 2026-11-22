import { onBeforeUnmount, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { prefersReducedMotion } from '@/utils/motion'

export const AUTH_SWITCH_STEPS = Object.freeze({
  [ROUTE_NAMES.AUTH_LOGIN]: 0,
  [ROUTE_NAMES.AUTH_REGISTER]: 1,
  [ROUTE_NAMES.AUTH_FORGOT_PASSWORD]: 1,
})

export const AUTH_MOTION = Object.freeze({
  leaveMs: 170,
  enterMs: 420,
  resizeMs: 460,
  settleMs: 1600,
  shiftPx: 32,
  easeOut: 'cubic-bezier(0.22, 1, 0.36, 1)',
  easeIn: 'cubic-bezier(0.55, 0, 1, 0.45)',
})

const RESIZING = 'is-resizing'

export function authSwitchStep(from, to) {
  if (!Object.hasOwn(AUTH_SWITCH_STEPS, from) || !Object.hasOwn(AUTH_SWITCH_STEPS, to)) return 0
  if (prefersReducedMotion()) return 0
  return AUTH_SWITCH_STEPS[to] >= AUTH_SWITCH_STEPS[from] ? 1 : -1
}

function inlineSign() {
  return document.documentElement.dir === 'rtl' ? -1 : 1
}

function canAnimate(element) {
  return typeof element?.animate === 'function'
}

function finishAfterPatch(done) {
  queueMicrotask(done)
}

function focusHeading(page) {
  const heading = page.querySelector('h1')
  if (!heading) return
  if (!heading.hasAttribute('tabindex')) heading.setAttribute('tabindex', '-1')
  heading.focus({ preventScroll: true })
}

function shouldMoveFocus(page, hadFocus) {
  const active = document.activeElement
  if (active && page.contains(active)) return false
  return hadFocus || !active || active === document.body
}

function useViewportResize(viewportRef, contentRef) {
  let height = 0
  let settleUntil = 0
  let observer = null

  function animateTo(viewport, next) {
    const from = viewport.getAnimations().length ? viewport.getBoundingClientRect().height : height
    viewport.getAnimations().forEach((animation) => animation.cancel())
    viewport.classList.add(RESIZING)
    const animation = viewport.animate([{ blockSize: `${from}px` }, { blockSize: `${next}px` }], {
      duration: AUTH_MOTION.resizeMs,
      easing: AUTH_MOTION.easeOut,
    })
    animation.onfinish = () => viewport.classList.remove(RESIZING)
  }

  function follow(next) {
    const viewport = viewportRef.value
    const settling = performance.now() < settleUntil
    if (settling && height && next !== height && canAnimate(viewport)) animateTo(viewport, next)
    height = next
  }

  function onResize(entries) {
    follow(entries.at(-1)?.contentRect.height ?? height)
  }

  function followNow() {
    if (contentRef.value) follow(contentRef.value.getBoundingClientRect().height)
  }

  function settle(extraMs = 0) {
    settleUntil = performance.now() + extraMs + AUTH_MOTION.settleMs
  }

  onMounted(() => {
    if (typeof ResizeObserver !== 'function' || !contentRef.value) return
    observer = new ResizeObserver(onResize)
    observer.observe(contentRef.value)
  })

  onBeforeUnmount(() => observer?.disconnect())

  return { settle, followNow }
}

export function useAuthPageTransition({ viewportRef, contentRef }) {
  const route = useRoute()
  const { settle, followNow } = useViewportResize(viewportRef, contentRef)
  let step = 0
  let hadFocus = false

  watch(
    () => route.name,
    (to, from) => {
      step = authSwitchStep(from, to)
    },
    { flush: 'sync' },
  )

  function offset() {
    return AUTH_MOTION.shiftPx * step * inlineSign()
  }

  function onBeforeLeave() {
    hadFocus = Boolean(viewportRef.value?.contains(document.activeElement))
    if (step) settle(AUTH_MOTION.leaveMs)
  }

  function onLeave(page, done) {
    if (!step || !canAnimate(page)) return finishAfterPatch(done)
    page
      .animate(
        [
          { opacity: 1, translate: '0 0' },
          { opacity: 0, translate: `${-offset()}px 0` },
        ],
        { duration: AUTH_MOTION.leaveMs, easing: AUTH_MOTION.easeIn, fill: 'forwards' },
      )
      .finished.then(done, done)
  }

  function onEnter(page, done) {
    if (shouldMoveFocus(page, hadFocus)) focusHeading(page)
    if (!step || !canAnimate(page)) return done()
    followNow()
    page
      .animate(
        [
          { opacity: 0, translate: `${offset()}px 0` },
          { opacity: 1, translate: '0 0' },
        ],
        { duration: AUTH_MOTION.enterMs, easing: AUTH_MOTION.easeOut },
      )
      .finished.then(done, done)
  }

  return { onBeforeLeave, onLeave, onEnter }
}
