import { computed, ref } from 'vue'

const GAP_PX = 8
const LABEL_SELECTOR = '.sidebar__label'

function labelIsHidden(element) {
  const label = element.querySelector(LABEL_SELECTOR)
  return !label || label.getBoundingClientRect().width === 0
}

export function useSidebarTooltip() {
  const hint = ref(null)

  function show(label, element) {
    if (!element || !labelIsHidden(element)) return
    const rect = element.getBoundingClientRect()
    const isRtl = document.documentElement.dir === 'rtl'
    hint.value = {
      label,
      top: rect.top + rect.height / 2,
      inlineStart: isRtl ? window.innerWidth - rect.left + GAP_PX : rect.right + GAP_PX,
    }
  }

  function hide() {
    hint.value = null
  }

  const style = computed(() =>
    hint.value
      ? { top: `${hint.value.top}px`, insetInlineStart: `${hint.value.inlineStart}px` }
      : {},
  )

  return { hint, style, show, hide }
}
