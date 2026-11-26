import { onBeforeUnmount, onMounted, watch } from 'vue'
import { prefersReducedMotion } from '@/utils/motion'
import { hasFinePointer } from '@/utils/landing/pointer'
import { copyShield, drawDotField, paletteFor, sparkCells } from '@/utils/landing/dotField'

const MAX_PIXEL_RATIO = 1.5
const FOLLOW = 0.1
const POWER_EASE = 0.07
const PATTERN_SHIFT_X = 18
const PATTERN_SHIFT_Y = 12
const COPY_MARGIN_X = 30
const COPY_MARGIN_Y = 20
const OFFSCREEN = -9999

export function useHeroDots({ canvasRef, pinRef, copyRef, patternRef, isDark }) {
  const fine = hasFinePointer()
  const lens = { x: OFFSCREEN, y: OFFSCREEN, active: false, power: 0 }
  const target = { x: OFFSCREEN, y: OFFSCREEN, inside: false }
  let ctx = null
  let width = 0
  let height = 0
  let sparks = []
  let tick = 0
  let frame = 0
  let visible = true
  let observer = null
  let still = false

  function size() {
    const canvas = canvasRef.value
    if (!canvas || !ctx) return
    const rect = canvas.getBoundingClientRect()
    const ratio = Math.min(window.devicePixelRatio || 1, MAX_PIXEL_RATIO)
    width = rect.width
    height = rect.height
    canvas.width = Math.round(width * ratio)
    canvas.height = Math.round(height * ratio)
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0)
    sparks = sparkCells(width, height)
  }

  function copyBox() {
    const copy = copyRef.value?.getBoundingClientRect()
    const canvas = canvasRef.value.getBoundingClientRect()
    if (!copy) return { x0: 0, x1: 0, y0: 0, y1: 0 }
    return {
      x0: copy.left - canvas.left - COPY_MARGIN_X,
      x1: copy.right - canvas.left + COPY_MARGIN_X,
      y0: copy.top - canvas.top - COPY_MARGIN_Y,
      y1: copy.bottom - canvas.top + COPY_MARGIN_Y,
    }
  }

  function wander() {
    target.x = width * (0.5 + 0.36 * Math.sin(tick * 0.006))
    target.y = height * (0.62 + 0.22 * Math.cos(tick * 0.0085))
    target.inside = true
  }

  function follow() {
    if (!fine) wander()
    if (lens.x <= OFFSCREEN + 1 && target.x > OFFSCREEN)
      Object.assign(lens, { x: target.x, y: target.y })
    lens.x += (target.x - lens.x) * FOLLOW
    lens.y += (target.y - lens.y) * FOLLOW
    lens.power += ((target.inside ? 1 : 0) - lens.power) * POWER_EASE
    lens.active = lens.power > 0.02
  }

  function shiftPattern() {
    const pattern = patternRef.value
    if (!pattern || lens.x <= OFFSCREEN + 1) return
    pattern.style.setProperty(
      '--px',
      (-(lens.x / width - 0.5) * PATTERN_SHIFT_X * lens.power).toFixed(2),
    )
    pattern.style.setProperty(
      '--py',
      (-(lens.y / height - 0.5) * PATTERN_SHIFT_Y * lens.power).toFixed(2),
    )
  }

  function draw() {
    if (!ctx || !width) return
    if (!still) {
      follow()
      shiftPattern()
    }
    const visibility = 1 - (parseFloat(pinRef.value?.style.getPropertyValue('--co')) || 0)
    drawDotField(ctx, {
      width,
      height,
      palette: paletteFor(isDark.value),
      lens,
      tick,
      sparks,
      box: copyBox(),
      visibility,
      shield: copyShield(copyBox(), visibility),
    })
  }

  function loop() {
    if (visible && !document.hidden) {
      tick += 1
      draw()
    }
    frame = requestAnimationFrame(loop)
  }

  function onPointerMove(event) {
    const rect = canvasRef.value.getBoundingClientRect()
    target.x = event.clientX - rect.left
    target.y = event.clientY - rect.top
    target.inside = true
  }

  function onPointerLeave() {
    target.inside = false
  }

  function onResize() {
    size()
    draw()
  }

  onMounted(() => {
    ctx = canvasRef.value?.getContext?.('2d') ?? null
    if (!ctx) return
    size()
    window.addEventListener('resize', onResize)
    still = prefersReducedMotion()
    if (still) {
      draw()
      return
    }
    if (fine) {
      pinRef.value?.addEventListener('pointermove', onPointerMove, { passive: true })
      pinRef.value?.addEventListener('pointerleave', onPointerLeave)
    }
    observer = new IntersectionObserver((entries) => {
      visible = entries[0]?.isIntersecting ?? true
    })
    observer.observe(pinRef.value)
    frame = requestAnimationFrame(loop)
  })

  watch(isDark, () => draw())

  onBeforeUnmount(() => {
    cancelAnimationFrame(frame)
    observer?.disconnect()
    window.removeEventListener('resize', onResize)
    pinRef.value?.removeEventListener('pointermove', onPointerMove)
    pinRef.value?.removeEventListener('pointerleave', onPointerLeave)
  })
}
