import { onScopeDispose, toValue, watch } from 'vue'
import { DROPDOWN_METRICS, END_ALIGNED_PLACEMENTS, POPUP_SIDES } from '@/constants/dropdown'
import { computePlacement, isRectInView } from '@/utils/dropdown/placement'
import { rootFontSize, toPixels } from '@/utils/dropdown/cssLength'

const VARIABLES = Object.freeze({
  inlineStart: '--dropdown-inline-start',
  blockStart: '--dropdown-block-start',
  blockEnd: '--dropdown-block-end',
  maxBlockSize: '--dropdown-max-block-size',
  anchorInlineSize: '--dropdown-anchor-inline-size',
  viewportBlockSize: '--dropdown-viewport-block-size',
})

function metricsInPixels() {
  const remSize = rootFontSize()
  return Object.fromEntries(
    Object.entries(DROPDOWN_METRICS).map(([key, length]) => [key, toPixels(length, remSize)]),
  )
}

function viewportSize() {
  const root = document.documentElement
  return { width: root.clientWidth, height: root.clientHeight }
}

function supportsPopover(element) {
  return typeof element?.showPopover === 'function'
}

function showInTopLayer(element) {
  if (supportsPopover(element)) element.showPopover()
}

function hideFromTopLayer(element) {
  if (supportsPopover(element)) element.hidePopover()
}

function clipsOverflow(element) {
  const { overflowX, overflowY } = getComputedStyle(element)
  return overflowX !== 'visible' || overflowY !== 'visible'
}

function intersects(rect, other) {
  return (
    rect.bottom > other.top &&
    rect.top < other.bottom &&
    rect.right > other.left &&
    rect.left < other.right
  )
}

export function isAnchorVisible(anchor) {
  const rect = anchor.getBoundingClientRect()
  if (!isRectInView(rect, viewportSize())) return false
  for (let node = anchor.parentElement; node && node !== document.body; node = node.parentElement) {
    if (clipsOverflow(node) && !intersects(rect, node.getBoundingClientRect())) return false
  }
  return true
}

function writePlacement(element, placement) {
  const sideVariable = placement.side === POPUP_SIDES.above ? 'blockEnd' : 'blockStart'
  const otherVariable = sideVariable === 'blockEnd' ? 'blockStart' : 'blockEnd'
  element.style.setProperty(VARIABLES.inlineStart, `${placement.inlineStart}px`)
  element.style.setProperty(VARIABLES[sideVariable], `${placement.blockOffset}px`)
  element.style.removeProperty(VARIABLES[otherVariable])
  element.style.setProperty(VARIABLES.maxBlockSize, `${placement.maxBlockSize}px`)
  element.dataset.side = placement.side
}

export function useAnchoredPopup({
  anchor,
  boundary,
  popup,
  isOpen,
  isSheet,
  placement,
  onDismiss,
}) {
  let frame = 0
  let isPending = false
  let resizeObserver = null

  function position() {
    const anchorElement = toValue(anchor)
    const popupElement = toValue(popup)
    if (!anchorElement || !popupElement) return
    const anchorRect = anchorElement.getBoundingClientRect()
    popupElement.style.setProperty(VARIABLES.anchorInlineSize, `${anchorRect.width}px`)
    writePlacement(
      popupElement,
      computePlacement({
        anchor: anchorRect,
        popup: { inlineSize: popupElement.offsetWidth, blockSize: popupElement.scrollHeight },
        viewport: viewportSize(),
        direction: getComputedStyle(anchorElement).direction,
        align: END_ALIGNED_PLACEMENTS.includes(toValue(placement)) ? 'end' : 'start',
        metrics: metricsInPixels(),
      }),
    )
  }

  function update() {
    isPending = false
    const anchorElement = toValue(anchor)
    if (anchorElement && !isAnchorVisible(anchorElement)) {
      onDismiss()
      return
    }
    position()
  }

  function isScrollInsidePopup(event) {
    return (
      event?.type === 'scroll' &&
      event.target instanceof Node &&
      toValue(popup)?.contains(event.target)
    )
  }

  function schedule(event) {
    if (isScrollInsidePopup(event)) return
    if (isPending) return
    isPending = true
    frame = requestAnimationFrame(update)
  }

  function isInside(target) {
    const elements = [toValue(popup), toValue(boundary) ?? toValue(anchor)]
    return elements.some((element) => element?.contains(target))
  }

  function onPointerDown(event) {
    if (!isInside(event.target)) onDismiss()
  }

  function fitSheetToViewport() {
    const sheet = toValue(popup)
    const height = window.visualViewport?.height
    if (sheet && height) sheet.style.setProperty(VARIABLES.viewportBlockSize, `${height}px`)
  }

  function stopListening() {
    cancelAnimationFrame(frame)
    isPending = false
    window.removeEventListener('scroll', schedule, true)
    window.removeEventListener('resize', schedule)
    document.removeEventListener('pointerdown', onPointerDown, true)
    window.visualViewport?.removeEventListener('resize', fitSheetToViewport)
    resizeObserver?.disconnect()
    resizeObserver = null
  }

  function listenAsPopup() {
    window.addEventListener('scroll', schedule, true)
    window.addEventListener('resize', schedule)
    document.addEventListener('pointerdown', onPointerDown, true)
    if (typeof ResizeObserver === 'undefined') return
    resizeObserver = new ResizeObserver(() => schedule())
    resizeObserver.observe(toValue(popup))
  }

  function listenAsSheet() {
    fitSheetToViewport()
    window.visualViewport?.addEventListener('resize', fitSheetToViewport)
  }

  watch(
    [() => toValue(isOpen), () => toValue(isSheet)],
    ([open, sheet]) => {
      stopListening()
      const element = toValue(popup)
      if (!open || !element) {
        hideFromTopLayer(element)
        return
      }
      showInTopLayer(element)
      if (sheet) {
        listenAsSheet()
        return
      }
      position()
      listenAsPopup()
    },
    { flush: 'post' },
  )

  onScopeDispose(stopListening)

  return { reposition: schedule }
}
