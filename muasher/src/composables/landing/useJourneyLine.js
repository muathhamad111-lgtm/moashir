import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { prefersReducedMotion } from '@/utils/motion'
import { clamp } from '@/utils/landing/easing'
import { dotOffset, journeyPath } from '@/utils/landing/journeyPath'
import { LOCALE_DIR } from '@/constants/locales'
import { useScrollFrame } from './useScrollFrame'

const FILL_LINE = 0.62
const DOT_TOLERANCE = 2

export function useJourneyLine(frameRef, rowSelector, textSelector) {
  const { locale } = useI18n()
  const layout = ref({ width: 0, height: 0, d: '', length: 1, dots: [], stops: [] })
  const filled = ref(0)
  let observer = null
  let pendingMeasure = 0

  function measure() {
    const frame = frameRef.value
    if (!frame) return
    const box = frame.getBoundingClientRect()
    const isRtl = LOCALE_DIR[locale.value] === 'rtl'
    const rows = [...frame.querySelectorAll(rowSelector)].map((row) => {
      const rowBox = row.getBoundingClientRect()
      const textBox = row.querySelector(textSelector).getBoundingClientRect()
      return {
        x: dotOffset(textBox, box, isRtl),
        top: rowBox.top - box.top,
        bottom: rowBox.bottom - box.top,
        middle: textBox.top - box.top + textBox.height / 2,
      }
    })
    const path = journeyPath(rows)
    layout.value = {
      width: box.width,
      height: box.height,
      d: path.d,
      length: path.length,
      stops: path.stops,
      dots: rows.map((row) => ({ x: row.x, y: row.middle })),
    }
    render()
  }

  function render() {
    const frame = frameRef.value
    if (!frame) return
    if (prefersReducedMotion()) {
      filled.value = 1
      return
    }
    const box = frame.getBoundingClientRect()
    filled.value = clamp((window.innerHeight * FILL_LINE - box.top) / Math.max(1, box.height))
  }

  const reached = computed(() => {
    const at = filled.value * layout.value.length
    return layout.value.stops.map((stop) => at >= stop - DOT_TOLERANCE)
  })
  const dashOffset = computed(() => (layout.value.length * (1 - filled.value)).toFixed(1))

  useScrollFrame(render, { onResize: measure })

  onMounted(() => {
    measure()
    if (typeof ResizeObserver === 'function') {
      observer = new ResizeObserver(() => {
        cancelAnimationFrame(pendingMeasure)
        pendingMeasure = requestAnimationFrame(measure)
      })
      observer.observe(frameRef.value)
    }
    document.fonts?.ready?.then(measure)
  })

  watch(locale, () => nextTick(measure))

  onBeforeUnmount(() => {
    observer?.disconnect()
    cancelAnimationFrame(pendingMeasure)
  })

  return { layout, reached, dashOffset, measure }
}
