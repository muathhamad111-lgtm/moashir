const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

export function focusablesIn(container) {
  return Array.from(container?.querySelectorAll(FOCUSABLE) ?? [])
}

export function trapTabKey(event, container) {
  const elements = focusablesIn(container)
  if (!elements.length) {
    event.preventDefault()
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
