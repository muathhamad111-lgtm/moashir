import { gsap } from 'gsap'

function prefersReducedMotion() {
  return typeof window !== 'undefined' && window.matchMedia
    ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
    : false
}

export const reveal = {
  mounted(el, binding) {
    if (prefersReducedMotion()) return
    const options = binding.value ?? {}
    const staggerChildren = Boolean(options.stagger)
    const targets = staggerChildren ? Array.from(el.children) : el
    if (staggerChildren && !targets.length) return
    el._revealCtx = gsap.context(() => {
      gsap.from(targets, {
        autoAlpha: 0,
        y: options.y ?? 20,
        duration: options.duration ?? 0.55,
        ease: 'power2.out',
        stagger: staggerChildren ? (options.step ?? 0.08) : 0,
        clearProps: 'transform,visibility,opacity',
      })
    }, el)
  },
  unmounted(el) {
    el._revealCtx?.revert()
    el._revealCtx = null
  },
}
