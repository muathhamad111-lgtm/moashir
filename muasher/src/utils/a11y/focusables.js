export const FOCUSABLE_SELECTOR = [
  'a[href]',
  'area[href]',
  'button',
  'input:not([type="hidden"])',
  'select',
  'textarea',
  'iframe',
  'object',
  'embed',
  'audio[controls]',
  'video[controls]',
  'summary',
  '[contenteditable]:not([contenteditable="false"])',
  '[tabindex]',
].join(', ')

function isReachable(element) {
  return (
    element.getAttribute('tabindex') !== '-1' &&
    !element.matches(':disabled') &&
    !element.closest('[hidden], [inert]')
  )
}

export function focusablesIn(container) {
  return Array.from(container?.querySelectorAll(FOCUSABLE_SELECTOR) ?? []).filter(isReachable)
}

export function trapTabKey(event, container) {
  const elements = focusablesIn(container)
  if (!elements.length) {
    event.preventDefault()
    container?.focus()
    return
  }
  const first = elements[0]
  const last = elements[elements.length - 1]
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}
