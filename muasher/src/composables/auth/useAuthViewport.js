import { inject, onBeforeUnmount, onMounted, provide } from 'vue'
import { AUTH_MOTION, canAnimate } from '@/composables/auth/authSlide'

const RESIZING = 'is-resizing'
const AUTH_VIEWPORT = Symbol('authViewport')
const STILL_VIEWPORT = Object.freeze({ settle: () => {}, followNow: () => {} })

export function useAuthViewport(viewportRef, contentRef) {
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

  const viewport = { settle, followNow }
  provide(AUTH_VIEWPORT, viewport)
  return viewport
}

export function injectAuthViewport() {
  return inject(AUTH_VIEWPORT, STILL_VIEWPORT)
}
